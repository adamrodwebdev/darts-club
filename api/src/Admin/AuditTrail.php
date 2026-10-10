<?php

namespace App\Admin;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Enregistre une action d'administration (persistée au prochain flush). */
final class AuditTrail
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param array<string, scalar|null> $details */
    public function record(User $actor, string $action, ?string $target = null, array $details = []): void
    {
        $this->em->persist(new AuditLog(
            mb_substr($actor->displayName()->value, 0, 80),
            $actor->getId(),
            $action,
            null !== $target ? mb_substr($target, 0, 120) : null,
            $details,
        ));
    }
}
