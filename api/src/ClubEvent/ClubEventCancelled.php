<?php

namespace App\ClubEvent;

use App\Event\DomainEvent;
use Symfony\Component\Uid\Uuid;

/** Événement annulé. */
final readonly class ClubEventCancelled implements DomainEvent
{
    public function __construct(
        public Uuid $eventId,
    ) {
    }
}
