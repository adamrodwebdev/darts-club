<?php

namespace App\EventAccounts;

use App\Membership\Money;

/** Résultat d'un bilan : recettes moins dépenses, en centimes, négatif en cas de perte. */
final readonly class SignedAmount
{
    public function __construct(
        public int $cents,
    ) {
    }

    public static function difference(Money $plus, Money $minus): self
    {
        return new self($plus->cents - $minus->cents);
    }

    /** « 402.40 », « -12.40 » (format d'échange API / CSV) */
    public function toDecimal(): string
    {
        $absolute = abs($this->cents);

        return \sprintf('%s%d.%02d', $this->cents < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);
    }
}
