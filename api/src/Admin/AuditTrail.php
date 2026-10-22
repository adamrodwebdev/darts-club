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

    /** @param array<string, scalar|null> $details */
    public function record(User $actor, string $action, ?string $target = null, array $details = []): void
    {
        $this->em->persist(new AuditLog(
            actorName: mb_substr($actor->displayName()->value, 0, 80),
            actorId: $actor->getId(),
            action: $action,
            target: null !== $target ? mb_substr($target, 0, 120) : null,
            now: $this->clock->now(),
            details: $details,
        ));
    }
}
