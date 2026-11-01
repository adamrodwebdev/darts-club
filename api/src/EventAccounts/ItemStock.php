<?php

namespace App\EventAccounts;

use App\Membership\Money;

/** Achat d'un article : contenant (fût, pack), quantités en contenants, prix d'un contenant. */
final readonly class ItemStock
{
    public function __construct(
        public ContainerSpec $container,
        public ItemQuantities $quantities,
        public Money $containerPrice,
    ) {
    }

    /** En unités de stock : contenants gardés (achetés moins rendus) fois leur contenance */
    public function units(): int
    {
        return $this->quantities->kept() * $this->container->size;
    }
}
