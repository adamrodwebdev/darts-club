<?php

namespace App\Message;

use App\Notification\NotificationMessage;

/** Livraison à un seul adhérent : une erreur n'affecte pas les autres. */
final readonly class DeliverNotification
{
    public function __construct(
        public string $userId,
        public NotificationMessage $content,
    ) {
    }
}
