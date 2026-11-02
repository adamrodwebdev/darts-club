<?php

namespace App\Evening;

use App\EventAccounts\Station;
use App\Membership\Money;

/** Article et format commandés, tels que la carte les proposait au moment de la commande. */
final readonly class OrderedItem
{
    public function __construct(
        public string $itemId,
        public string $label,
        public Station $station,
        public string $format,
        public ?int $volume,
        public Money $price,
    ) {
    }
}
