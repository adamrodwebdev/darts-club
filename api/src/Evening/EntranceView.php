<?php

declare(strict_types=1);

namespace App\Evening;

use App\Entity\ClubEvent;
use App\Entity\TokenSale;
use App\EventAccounts\CashSettings;
use App\EventAccounts\TokenKind;
use App\Repository\EventAccountsRepository;
use App\Repository\TokenSaleRepository;

/**
 * Écran de l'accueil : jetons et sachets en vente, dernières ventes, argent encaissé ce soir
 * par moyen de paiement (sommé en base à chaque lecture : aucun compteur qui dérive).
 */
final class EntranceView
{
    private const LATEST = 30;

    public function __construct(
        private readonly EventAccountsRepository $books,
        private readonly TokenSaleRepository $sales,
    ) {
    }

    /** @return array<string, mixed> */
    public function of(ClubEvent $event): array
    {
        $cash = $this->books->findForEvent($event)?->cash() ?? CashSettings::clubDefaults();

        return [
            'event' => [
                'id' => $event->getId()->toRfc4122(),
                'title' => $event->getTitle(),
                'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
            ],
            'tokensEnabled' => $cash->tokensEnabled,
            'tokens' => array_map(static fn (TokenKind $token): array => [
                'name' => $token->name,
                'value' => $token->value->toDecimal(),
                'bag' => $token->bag,
                'color' => $token->color?->value,
            ], $cash->tokens),
            'cashFloat' => $cash->cashFloat->toDecimal(),
            'sales' => array_map(static fn (TokenSale $sale): array => $sale->toArray(), $this->sales->latestFor($event, self::LATEST)),
            'taken' => [
                'cash' => $this->sales->takenWith($event, PaymentMethod::Cash)->toDecimal(),
                'card' => $this->sales->takenWith($event, PaymentMethod::Card)->toDecimal(),
            ],
        ];
    }
}
