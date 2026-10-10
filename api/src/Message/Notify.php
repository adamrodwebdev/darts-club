<?php

namespace App\Message;

use App\Notification\NotificationMessage;
use App\Notification\Recipients;

/** Diffusion : éclatée en un DeliverNotification par destinataire. */
final readonly class Notify
{
    public function __construct(
        public NotificationMessage $content,
        public Recipients $recipients,
    ) {
    }
}
