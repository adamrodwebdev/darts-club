<?php

declare(strict_types=1);

namespace App\Evening;

use Symfony\Component\Uid\Uuid;

/** Ce que l'appareil de l'accueil demande : quantités par nom de jeton, payées en espèces ou par carte. */
final readonly class SaleOrder
{
    /** @param array<string, int> $counts */
    public function __construct(
        public Uuid $id,
        public PaymentMethod $method,
        public array $counts,
    ) {
    }
}
