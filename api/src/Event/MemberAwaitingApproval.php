<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Adresse confirmée, compte pas encore validé par le bureau : le président et l'administrateur sont prévenus. */
final readonly class MemberAwaitingApproval implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
