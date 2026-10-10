<?php

namespace App\Membership;

use App\Event\DomainEvent;
use Symfony\Component\Uid\Uuid;

/** La cotisation d'un adhérent vient d'être entièrement réglée. */
final readonly class MembershipPaid implements DomainEvent
{
    public function __construct(
        public Uuid $membershipId,
    ) {
    }
}
