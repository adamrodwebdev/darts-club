<?php

namespace App\EventAccounts;

use App\Membership\Money;

/** Montant prévu, puis réel (inconnu tant que la soirée n'a pas eu lieu). */
final readonly class EntryAmounts
{
    public function __construct(
        public Money $planned,
        public ?Money $actual = null,
    ) {
    }

    /** Réel s'il est connu, zéro sinon (totaux réels) */
    public function actualOrZero(): Money
    {
        return $this->actual ?? Money::zero();
    }
}
