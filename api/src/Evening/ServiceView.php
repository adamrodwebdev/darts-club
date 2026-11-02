<?php

namespace App\Evening;

use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EveningOrder;
use App\EventAccounts\CashSettings;
use App\EventAccounts\SaleFormat;
use App\EventAccounts\Station;
use App\EventAccounts\TokenKind;
use App\Repository\EveningOrderRepository;
use App\Repository\EventAccountsRepository;

/**
 * Écrans du bar et de la cuisine : carte disponible (épuisés signalés), commandes par étape,
 * stock de la cuisine. Lu à chaque action et à chaque diffusion : tous les appareils voient la même
 * chose.
 */
final class ServiceView
{
    private const HANDLED = 6;

    public function __construct(
        private readonly EventAccountsRepository $books,
        private readonly EveningOrderRepository $orders,
    ) {
    }

    /** @return array<string, mixed> */
    public function bar(ClubEvent $event): array
    {
        $accounts = $this->books->findForEvent($event);
        $cash = $accounts?->cash() ?? CashSettings::clubDefaults();

        return [
            'event' => self::event($event),
            'nextNumber' => $accounts?->nextOrderNumber() ?? 1,
            'tokensEnabled' => $cash->tokensEnabled,
            // Jetons rendus par le bar en monnaie (« 21 verts + 1 jaune ») : pas les sachets
            'tokens' => array_values(array_map(
                static fn (TokenKind $token): array => ['name' => $token->name, 'value' => $token->value->toDecimal(), 'color' => $token->color?->value],
                array_filter($cash->tokens, static fn (TokenKind $token): bool => !$token->bag),
            )),
            'menu' => array_map(self::item(...), $accounts?->items() ?? []),
            'orders' => [
                'waiting' => self::list($this->orders->atStep($event, KitchenStatus::Waiting)),
                'ready' => self::list($this->orders->atStep($event, KitchenStatus::Ready)),
                'delivered' => self::list($this->orders->latestAt($event, [KitchenStatus::Delivered], self::HANDLED)),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function kitchen(ClubEvent $event): array
    {
        $kitchen = array_values(array_filter($this->books->findForEvent($event)?->items() ?? [], static fn (BarItem $item): bool => Station::Kitchen === $item->serving()->station));

        return [
            'event' => self::event($event),
            'pending' => self::list($this->orders->atStep($event, KitchenStatus::Waiting)),
            'sent' => self::list($this->orders->latestAt($event, [KitchenStatus::Ready, KitchenStatus::Delivered], self::HANDLED)),
            'stock' => array_map(self::item(...), $kitchen),
        ];
    }

    /** @return array<string, string> */
    private static function event(ClubEvent $event): array
    {
        return [
            'id' => $event->getId()->toRfc4122(),
            'title' => $event->getTitle(),
            'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private static function item(BarItem $item): array
    {
        $serving = $item->serving();
        $availability = $item->availability();

        return [
            'id' => $item->getId()->toRfc4122(),
            'label' => $item->label(),
            'station' => $serving->station->value,
            'unit' => $serving->unit->value,
            'garnishes' => array_map(static fn (string $garnish): array => ['name' => $garnish, 'out' => $availability->lacks($garnish)], $serving->garnishes),
            'soldOut' => $item->isSoldOut(),
            'markedSoldOut' => $availability->soldOut,
            'left' => $item->left(),
            'formats' => array_map(static fn (SaleFormat $format): array => [
                'name' => $format->name,
                'volume' => $format->volume,
                'price' => $format->price->toDecimal(),
            ], $item->formats()->all),
        ];
    }

    /**
     * @param list<EveningOrder> $orders
     *
     * @return list<array<string, mixed>>
     */
    private static function list(array $orders): array
    {
        return array_map(static fn (EveningOrder $order): array => $order->toArray(), $orders);
    }
}
