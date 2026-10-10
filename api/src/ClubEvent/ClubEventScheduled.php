<?php

namespace App\ClubEvent;

use App\Event\DomainEvent;
use Symfony\Component\Uid\Uuid;

/** Un événement vient d'être planifié. */
final readonly class ClubEventScheduled implements DomainEvent
{
    public function __construct(
        public Uuid $eventId,
    ) {
    }
}
