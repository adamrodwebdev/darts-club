<?php

namespace App\ClubEvent;

use App\Event\DomainEvent;
use Symfony\Component\Uid\Uuid;

/** Date ou heure modifiée. */
final readonly class ClubEventRescheduled implements DomainEvent
{
    public function __construct(
        public Uuid $eventId,
    ) {
    }
}
