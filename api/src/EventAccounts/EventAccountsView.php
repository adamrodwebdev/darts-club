<?php

namespace App\EventAccounts;

use App\ClubEvent\ClubTime;
use App\Entity\AccountsEntry;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EventAccounts;
use App\Entity\Receipt;
use App\Evening\Team;
use App\Membership\Money;
use App\Messaging\MessagingView;
use App\Repository\EveningTeamMemberRepository;
use App\Repository\EventAccountsRepository;
use App\Repository\TokenSaleRepository;

/**
 * Bilan d'une soirée pour le bureau : carte et stock, encaissement, équipes, lignes, totaux calculés
 * ici (seule source), compteurs de l'année.
 */
final class EventAccountsView
{
    public function __construct(
        private readonly EventAccountsRepository $books,
        private readonly EveningTeamMemberRepository $teams,
        private readonly MessagingView $members,
        private readonly TokenSaleRepository $sales,
    ) {
    }

    /** @return array<string, mixed> */
    public function of(ClubEvent $event, AccountsPermissions $permissions): array
    {
        $accounts = $this->books->findForEvent($event);
        $year = (int) $event->getStartsAt()->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE))->format('Y');
        $flags = $accounts?->flags() ?? new AccountsFlags();

        return [
            'event' => [
                'id' => $event->getId()->toRfc4122(),
                'title' => $event->getTitle(),
                'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
            ],
            'closed' => $accounts?->isClosed() ?? false,
            'closedAt' => $accounts?->closedAt()?->format(\DATE_ATOM),
            'closedBy' => $accounts?->closedBy()?->displayName()->value,
            'flags' => ['temporaryBar' => $flags->temporaryBar, 'taxExempt' => $flags->taxExempt],
            'items' => array_map(self::item(...), $accounts?->items() ?? []),
            'cash' => self::cash($accounts?->cash() ?? CashSettings::clubDefaults()) + ['frozen' => $this->sales->existsFor($event)],
            'teams' => $this->teams($event),
            'entries' => array_map(self::entry(...), $accounts?->entries() ?? []),
            'receipts' => array_map(static fn (Receipt $receipt): array => $receipt->toArray(), $accounts?->receipts() ?? []),
            'totals' => null === $accounts ? self::noTotals() : self::totals($accounts, $this->sales->entranceMoney($event)),
            'counters' => [
                'year' => $year,
                'temporaryBars' => ['used' => $this->books->temporaryBarsIn($year), 'limit' => AccountsFlags::TEMPORARY_BARS_PER_YEAR],
                'taxExempt' => ['used' => $this->books->taxExemptEventsIn($year), 'limit' => AccountsFlags::TAX_EXEMPT_EVENTS_PER_YEAR],
            ],
            'permissions' => ['edit' => $permissions->edit, 'editMenu' => $permissions->editMenu, 'reopen' => $permissions->reopen],
        ];
    }

    /** @return array<string, mixed> */
    private static function item(BarItem $item): array
    {
        $serving = $item->serving();
        $stock = $item->stock();

        return [
            'id' => $item->getId()->toRfc4122(),
            'position' => $item->position(),
            'label' => $item->label(),
            'station' => $serving->station->value,
            'unit' => $serving->unit->value,
            'garnishes' => $serving->garnishes,
            'alcoholic' => $serving->alcoholic,
            'container' => ['name' => $stock->container->name, 'size' => $stock->container->size],
            'planned' => $stock->quantities->planned,
            'bought' => $stock->quantities->bought,
            'returned' => $stock->quantities->returned,
            'purchasePrice' => $stock->containerPrice->toDecimal(),
            'formats' => array_map(static fn (SaleFormat $format): array => [
                'name' => $format->name,
                'volume' => $format->volume,
                'price' => $format->price->toDecimal(),
                'sold' => $format->sold,
            ], $item->formats()->all),
            'stock' => $stock->units(),
            'served' => $item->formats()->served(),
            'soldCount' => $item->formats()->soldCount(),
            'left' => $item->left(),
            'cost' => $item->cost()->toDecimal(),
            'income' => $item->income()->toDecimal(),
        ];
    }

    /** @return array<string, mixed> */
    private static function cash(CashSettings $cash): array
    {
        return [
            'tokensEnabled' => $cash->tokensEnabled,
            'tokens' => array_map(static fn (TokenKind $token): array => ['name' => $token->name, 'value' => $token->value->toDecimal(), 'bag' => $token->bag, 'color' => $token->color?->value], $cash->tokens),
            'cashFloat' => $cash->cashFloat->toDecimal(),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function teams(ClubEvent $event): array
    {
        $teams = array_fill_keys(array_map(static fn (Team $team): string => $team->value, Team::cases()), []);
        foreach ($this->teams->forEvent($event) as $member) {
            $teams[$member->team()->value][] = ['assignmentId' => $member->getId()->toRfc4122(), 'assignedAt' => $member->assignedAt()->format(\DATE_ATOM)] + $this->members->member($member->user());
        }

        return $teams;
    }

    /** @return array<string, mixed> */
    private static function entry(AccountsEntry $entry): array
    {
        $amounts = $entry->amounts();

        return [
            'id' => $entry->getId()->toRfc4122(),
            'position' => $entry->position(),
            'kind' => $entry->kind()->value,
            'label' => $entry->label(),
            'planned' => $amounts->planned->toDecimal(),
            'actual' => $amounts->actual?->toDecimal(),
        ];
    }

    /** @return array<string, string> */
    private static function totals(EventAccounts $accounts, ?Money $entrance): array
    {
        return [
            'barCosts' => $accounts->barCosts()->toDecimal(),
            // Buvette comptée au résultat : l'argent de l'accueil quand des jetons y ont été vendus
            'barIncome' => ($entrance ?? $accounts->barIncome())->toDecimal(),
            'barIncomeSource' => null === $entrance ? 'menu' : 'entrance',
            'barSales' => $accounts->barIncome()->toDecimal(),
            'costs' => $accounts->costs()->toDecimal(),
            'income' => $accounts->income($entrance)->toDecimal(),
            'result' => $accounts->result($entrance)->toDecimal(),
            'plannedCosts' => $accounts->plannedCosts()->toDecimal(),
            'plannedIncome' => $accounts->plannedIncome()->toDecimal(),
            'plannedResult' => $accounts->plannedResult()->toDecimal(),
        ];
    }

    /** @return array<string, string> */
    private static function noTotals(): array
    {
        return ['barIncomeSource' => 'menu'] + array_fill_keys(['barCosts', 'barIncome', 'barSales', 'costs', 'income', 'result', 'plannedCosts', 'plannedIncome', 'plannedResult'], '0.00');
    }
}
