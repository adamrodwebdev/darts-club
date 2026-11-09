<?php

declare(strict_types=1);

namespace App\Evening\Income;

use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Evening\KitchenStatus;
use App\Evening\PaymentMethod;
use App\EventAccounts\SaleFormat;
use App\EventAccounts\Station;
use App\Membership\Money;
use App\Repository\EventAccountsRepository;

/**
 * Recettes en direct d'une soirée, pour le bureau : argent de l'accueil, jetons dépensés au bar et en
 * cuisine, jetons en circulation, ventes et reste par article de la carte. Tout est recalculé ici à
 * chaque lecture : le site affiche, il ne compte pas.
 */
final class IncomeView
{
    public function __construct(
        private readonly EventAccountsRepository $books,
        private readonly EveningIncomes $incomes,
    ) {
    }

    /** @return array<string, mixed> */
    public function of(ClubEvent $event): array
    {
        $income = $this->incomes->for($event);
        $orders = $income->orders();

        return [
            'event' => [
                'id' => $event->getId()->toRfc4122(),
                'title' => $event->getTitle(),
                'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
            ],
            'tokensEnabled' => $income->tokensEnabled(),
            'entrance' => [
                'cash' => $income->taken(PaymentMethod::Cash)->toDecimal(),
                'card' => $income->taken(PaymentMethod::Card)->toDecimal(),
                'total' => $income->entrance()->toDecimal(),
                'cashFloat' => $income->cashFloat()->toDecimal(),
                'expectedCash' => $income->expectedCash()->toDecimal(),
                'tokens' => array_map(static fn (TokenTally $tally): array => [
                    'name' => $tally->token->name,
                    'value' => $tally->token->value->toDecimal(),
                    'bag' => $tally->token->bag,
                    'color' => $tally->token->color?->value,
                    'count' => $tally->count,
                    'amount' => $tally->amount()->toDecimal(),
                ], $income->tokens()),
            ],
            'bar' => ['amount' => $income->spentAt(Station::Bar)->toDecimal(), 'served' => $income->servedAt(Station::Bar)],
            'kitchen' => ['amount' => $income->spentAt(Station::Kitchen)->toDecimal(), 'served' => $income->servedAt(Station::Kitchen)],
            'spent' => $income->spent()->toDecimal(),
            'circulation' => $income->circulation()->toDecimal(),
            'orders' => [
                'count' => $orders->count,
                'waiting' => $orders->at(KitchenStatus::Waiting),
                'ready' => $orders->at(KitchenStatus::Ready),
                'cancelled' => $orders->at(KitchenStatus::Cancelled),
            ],
            'items' => self::items($this->books->findForEvent($event)?->items() ?? [], $income->items()),
        ];
    }

    /**
     * Articles de la carte dans son ordre, vendus ou non, puis ceux vendus ce soir et retirés depuis.
     *
     * @param list<BarItem>            $menu
     * @param array<string, ItemSales> $sales
     *
     * @return list<array<string, mixed>>
     */
    private static function items(array $menu, array $sales): array
    {
        $rows = [];
        foreach ($menu as $item) {
            $id = $item->getId()->toRfc4122();
            $rows[] = self::menuItem($item, $sales[$id] ?? null);
            unset($sales[$id]);
        }
        foreach ($sales as $id => $removed) {
            $rows[] = [
                'id' => $id,
                'label' => $removed->item->label,
                'station' => $removed->item->station->value,
                'unit' => null,
                'formats' => array_map(static fn (FormatSales $format): array => ['name' => $format->format, 'volume' => null, 'count' => $format->count, 'amount' => $format->amount->toDecimal()], $removed->formats),
                'amount' => $removed->amount()->toDecimal(),
                'served' => $removed->served,
                'stock' => null,
                'left' => null,
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private static function menuItem(BarItem $item, ?ItemSales $sales): array
    {
        $serving = $item->serving();
        $formats = array_map(static function (SaleFormat $format) use ($sales): array {
            $sold = $sales?->ofFormat($format->name);

            return ['name' => $format->name, 'volume' => $format->volume, 'count' => $sold->count ?? 0, 'amount' => ($sold->amount ?? Money::zero())->toDecimal()];
        }, $item->formats()->all);
        // Format vendu plus tôt dans la soirée, renommé ou retiré de la carte depuis
        foreach ($sales->formats ?? [] as $sold) {
            if (null === $item->formats()->find($sold->format)) {
                $formats[] = ['name' => $sold->format, 'volume' => null, 'count' => $sold->count, 'amount' => $sold->amount->toDecimal()];
            }
        }

        return [
            'id' => $item->getId()->toRfc4122(),
            'label' => $item->label(),
            'station' => $serving->station->value,
            'unit' => $serving->unit->value,
            'formats' => $formats,
            'amount' => ($sales?->amount() ?? Money::zero())->toDecimal(),
            'served' => null === $sales ? 0 : $sales->served,
            'stock' => $item->stock()->units(),
            'left' => $item->left(),
        ];
    }
}
