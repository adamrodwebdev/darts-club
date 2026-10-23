<?php

namespace App\Admin;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Enregistre une action d'administration (persistée au prochain flush). */
final class AuditTrail
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function record(User $actor, AuditEntry $entry): void
    {
        $this->em->persist(new AuditLog(
            actorName: mb_substr($actor->displayName()->value, 0, 80),
            actorId: $actor->getId(),
            action: $entry->action,
            target: null !== $entry->target ? mb_substr($entry->target, 0, 120) : null,
            now: $this->clock->now(),
            details: $entry->details,
        ));
    }
}
