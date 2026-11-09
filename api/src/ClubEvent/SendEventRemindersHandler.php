<?php

declare(strict_types=1);

namespace App\ClubEvent;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEventRemindersHandler
{
    public function __construct(
        private readonly EventReminders $reminders,
    ) {
    }

    public function __invoke(SendEventReminders $message): void
    {
        $this->reminders->sendDue();
    }
}
