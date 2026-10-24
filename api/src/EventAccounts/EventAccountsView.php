<?php

namespace App\EventAccounts;

use App\ClubEvent\ClubTime;
use App\Entity\AccountsEntry;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EventAccounts;
use App\Entity\Receipt;
use App\Repository\EventAccountsRepository;

/** Bilan d'une soirée pour le bureau : lignes, totaux calculés ici (seule source), compteurs de l'année. */
final class EventAccountsView
{
    public function __construct(
        private readonly EventAccountsRepository $books,
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
            'entries' => array_map(self::entry(...), $accounts?->entries() ?? []),
            'receipts' => array_map(static fn (Receipt $receipt): array => $receipt->toArray(), $accounts?->receipts() ?? []),
            'totals' => null === $accounts ? self::noTotals() : self::totals($accounts),
            'counters' => [
                'year' => $year,
                'temporaryBars' => ['used' => $this->books->temporaryBarsIn($year), 'limit' => AccountsFlags::TEMPORARY_BARS_PER_YEAR],
                'taxExempt' => ['used' => $this->books->taxExemptEventsIn($year), 'limit' => AccountsFlags::TAX_EXEMPT_EVENTS_PER_YEAR],
            ],
            'permissions' => ['edit' => $permissions->edit, 'reopen' => $permissions->reopen],
        ];
    }

    /** @return array<string, mixed> */
    private static function item(BarItem $item): array
    {
        $quantities = $item->quantities();
        $prices = $item->prices();

        return [
            'id' => $item->getId()->toRfc4122(),
            'position' => $item->position(),
            'label' => $item->label(),
            'planned' => $quantities->planned,
            'bought' => $quantities->bought,
            'returned' => $quantities->returned,
            'sold' => $quantities->sold,
            'left' => $quantities->left(),
            'purchasePrice' => $prices->purchase->toDecimal(),
            'salePrice' => $prices->sale->toDecimal(),
            'cost' => $item->cost()->toDecimal(),
            'income' => $item->income()->toDecimal(),
        ];
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
    private static function totals(EventAccounts $accounts): array
    {
        return [
            'barCosts' => $accounts->barCosts()->toDecimal(),
            'barIncome' => $accounts->barIncome()->toDecimal(),
            'costs' => $accounts->costs()->toDecimal(),
            'income' => $accounts->income()->toDecimal(),
            'result' => $accounts->result()->toDecimal(),
            'plannedCosts' => $accounts->plannedCosts()->toDecimal(),
            'plannedIncome' => $accounts->plannedIncome()->toDecimal(),
            'plannedResult' => $accounts->plannedResult()->toDecimal(),
        ];
    }

    /** @return array<string, string> */
    private static function noTotals(): array
    {
        return array_fill_keys(['barCosts', 'barIncome', 'costs', 'income', 'result', 'plannedCosts', 'plannedIncome', 'plannedResult'], '0.00');
    }
}
