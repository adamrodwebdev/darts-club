<?php

namespace App\Evening;

use Symfony\Component\Uid\Uuid;

/** Commande acceptée par le bilan de la soirée : numéro attribué, lignes figées. */
final readonly class PlacedOrder
{
    /** @param list<OrderLine> $lines */
    public function __construct(
        public Uuid $id,
        public int $number,
        public ?string $note,
        public array $lines,
    ) {
    }
}
