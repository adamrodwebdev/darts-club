<?php

namespace App\Announcement;

use App\Event\DomainEvent;
use Symfony\Component\Uid\Uuid;

final readonly class AnnouncementPublished implements DomainEvent
{
    public function __construct(
        public Uuid $announcementId,
    ) {
    }
}
