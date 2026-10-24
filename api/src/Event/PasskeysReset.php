<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Clés d'accès retirées par l'administrateur (appareil perdu) : le membre en est prévenu. */
final readonly class PasskeysReset implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
