<?php

namespace App\Messaging;

use App\Entity\Message;

/** Dernier message d'un fil et nombre de messages non lus par la personne qui le consulte */
final readonly class ConversationActivity
{
    public function __construct(
        public ?Message $last,
        public int $unread,
    ) {
    }
}
