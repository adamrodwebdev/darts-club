<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/** Compte validé par le bureau : l'espace membres lui est ouvert, il en est prévenu par email. */
final readonly class MemberApproved implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
