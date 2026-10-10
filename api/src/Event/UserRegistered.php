<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Un compte vient d'être créé. */
final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
        /** Email déjà vérifié par le fournisseur OAuth */
        public bool $viaOAuth = false,
    ) {
    }
}
