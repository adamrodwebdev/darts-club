<?php

namespace App\EventAccounts;

use App\Membership\Money;

/** Prix unitaires d'un article : payé au fournisseur, demandé à la buvette. */
final readonly class ItemPrices
{
    public function __construct(
        public Money $purchase,
        public Money $sale,
    ) {
    }
}
