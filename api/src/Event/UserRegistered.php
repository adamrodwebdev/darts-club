<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Événement de domaine : un compte vient d'être créé. */
final class UserRegistered
{
    public function __construct(
        public readonly Uuid $userId,
        /** Email déjà vérifié par le fournisseur OAuth */
        public readonly bool $viaOAuth = false,
    ) {
    }
}
