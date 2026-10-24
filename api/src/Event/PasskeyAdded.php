<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Nouvelle clé d'accès sur un compte : son titulaire est prévenu (si ce n'était pas lui, il le voit). */
final readonly class PasskeyAdded implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
