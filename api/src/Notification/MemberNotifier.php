<?php

namespace App\Notification;

use App\Message\Notify;
use Symfony\Component\Messenger\MessageBusInterface;

/** Point d'entrée unique pour notifier des adhérents (traitement asynchrone). */
final class MemberNotifier
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function notify(NotificationMessage $content, Recipients $recipients): void
    {
        $this->bus->dispatch(new Notify($content, $recipients));
    }
}
