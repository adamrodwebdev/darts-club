<?php

namespace App\Event;

/** Entité qui enregistre des événements de domaine, publiés après le flush. */
interface RecordsDomainEvents
{
    /** @return list<DomainEvent> */
    public function releaseEvents(): array;
}
