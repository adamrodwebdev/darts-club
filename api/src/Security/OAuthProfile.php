<?php

namespace App\Security;

/** Identité renvoyée par un fournisseur OAuth : compte chez lui, email s'il le communique et s'il l'a vérifié */
final readonly class OAuthProfile
{
    public function __construct(
        public string $provider,
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
    ) {
    }
}
