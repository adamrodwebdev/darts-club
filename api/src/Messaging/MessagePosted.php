<?php

namespace App\Messaging;

use App\Event\DomainEvent;
use Symfony\Component\Uid\Uuid;

final readonly class MessagePosted implements DomainEvent
{
    public function __construct(
        public Uuid $conversationId,
        public Uuid $messageId,
        public Uuid $authorId,
    ) {
    }
}
