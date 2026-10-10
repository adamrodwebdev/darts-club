<?php

namespace App\Message;

/**
 * Envoyé quand quelqu'un s'inscrit avec un email déjà utilisé : l'API
 * répond comme pour une inscription normale (pas d'énumération de comptes)
 * et seul le titulaire de l'adresse est prévenu.
 */
final class SendAccountExistsEmail
{
    public function __construct(
        public readonly string $userId,
    ) {
    }
}
