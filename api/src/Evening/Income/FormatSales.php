<?php

declare(strict_types=1);

namespace App\Evening\Income;

use App\Evening\OrderLine;
use App\Membership\Money;

/** Ventes d'un format (verre, pichet, portion) : quantité et jetons dépensés, aux prix figés dans les commandes. */
final readonly class FormatSales
{
    public function __construct(
        public string $format,
        public int $count,
        public Money $amount,
    ) {
    }

    public static function of(OrderLine $line): self
    {
        return new self($line->item->format, $line->count, $line->amount());
    }

    public function plus(OrderLine $line): self
    {
        return new self($this->format, $this->count + $line->count, $this->amount->add($line->amount()));
    }

    /** Même format, quelle que soit la casse (« verre », « Verre ») */
    public function isFormat(string $name): bool
    {
        return mb_strtolower($this->format) === mb_strtolower(trim($name));
    }
}
