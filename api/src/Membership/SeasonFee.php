<?php

namespace App\Membership;

/** Cotisation demandée pour une saison */
final readonly class SeasonFee
{
    public function __construct(
        public Season $season,
        public Money $amount,
    ) {
    }
}
