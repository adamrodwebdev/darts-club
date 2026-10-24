<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Un compte existant vient d'être relié à un fournisseur de connexion (Google, Discord). */
final readonly class ProviderLinked implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
        public string $provider,
    ) {
    }
}
