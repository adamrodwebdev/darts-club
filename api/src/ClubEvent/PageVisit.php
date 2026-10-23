<?php

namespace App\ClubEvent;

use App\Entity\User;

/** Vue de la page d'un événement : adhérent connecté, ou visiteur anonyme connu par son adresse IP et son navigateur */
final readonly class PageVisit
{
    public function __construct(
        public ?User $member,
        public ?string $ip,
        public string $userAgent,
    ) {
    }
}
