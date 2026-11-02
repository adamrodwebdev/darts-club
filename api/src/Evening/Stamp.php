<?php

namespace App\Evening;

use App\Entity\User;

/** Qui a fait une étape d'une commande (prise, prête, remise, annulée), et quand. */
final readonly class Stamp
{
    public function __construct(
        public User $by,
        public \DateTimeImmutable $at,
    ) {
    }
}
