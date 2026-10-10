<?php

namespace App\Message;

use App\Notification\NotificationContent;
use App\Notification\Recipients;

/** Diffusion : éclatée en un DeliverNotification par destinataire. */
final readonly class Notify
{
    public function __construct(
        public NotificationContent $content,
        public Recipients $recipients,
    ) {
    }
}
