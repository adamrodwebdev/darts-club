<?php

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/**
 * Quantités d'un article de la buvette : prévue, achetée, rendue au fournisseur (remboursée), vendue.
 * Ce qui reste (gardé pour une autre soirée ou offert) se déduit des trois dernières.
 */
final readonly class ItemQuantities
{
    public const MAX = 100_000;

    public function __construct(
        public int $planned,
        public int $bought,
        public int $returned,
        public int $sold,
    ) {
        foreach ([$planned, $bought, $returned, $sold] as $quantity) {
            if ($quantity < 0 || $quantity > self::MAX) {
                throw new InvalidValue('Quantité invalide : de 0 à %max%.', ['%max%' => self::MAX]);
            }
        }
        if ($returned > $bought) {
            throw new InvalidValue('On ne peut pas rendre plus que ce qui a été acheté.');
        }
        if ($sold > $bought - $returned) {
            throw new InvalidValue('Les ventes dépassent ce qui a été acheté, moins ce qui a été rendu.');
        }
    }

    /** Payé au fournisseur, une fois le rendu remboursé */
    public function kept(): int
    {
        return $this->bought - $this->returned;
    }

    /** Gardé pour une autre soirée, ou offert */
    public function left(): int
    {
        return $this->kept() - $this->sold;
    }
}
