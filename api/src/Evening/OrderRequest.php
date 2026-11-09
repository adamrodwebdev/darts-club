<?php

declare(strict_types=1);

namespace App\Evening;

/** Une ligne demandée par le bar : article, format, quantité, garnitures choisies. */
final readonly class OrderRequest
{
    /** @param list<string> $garnishes */
    public function __construct(
        public string $itemId,
        public string $format,
        public int $count,
        public array $garnishes = [],
    ) {
    }
}
