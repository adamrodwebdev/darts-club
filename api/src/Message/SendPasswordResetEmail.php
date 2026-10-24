<?php

namespace App\Message;

final class SendPasswordResetEmail
{
    public function __construct(
        public readonly string $userId,
        /** Lien envoyé par l'administrateur (l'adhérent ne l'a pas demandé lui-même sur le site) */
        public readonly bool $byAdministrator = false,
    ) {
    }
}
