<?php

declare(strict_types=1);

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/**
 * Quantités d'un article de la buvette, en contenants (fûts, packs, ou unités sans contenant) :
 * prévue, achetée, rendue au fournisseur (remboursée). Les ventes se comptent par format.
 */
final readonly class ItemQuantities
{
    public const MAX = 100_000;

    public function __construct(
        public int $planned,
        public int $bought,
        public int $returned,
    ) {
        foreach ([$planned, $bought, $returned] as $quantity) {
            if ($quantity < 0 || $quantity > self::MAX) {
                throw new InvalidValue('Quantité invalide : de 0 à %max%.', ['%max%' => self::MAX]);
            }
        }
        if ($returned > $bought) {
            throw new InvalidValue('On ne peut pas rendre plus que ce qui a été acheté.');
        }
    }

    /** Payé au fournisseur, une fois le rendu remboursé */
    public function kept(): int
    {
        return $this->bought - $this->returned;
    }
}
