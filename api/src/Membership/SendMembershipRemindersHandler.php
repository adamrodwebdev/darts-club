<?php

namespace App\Membership;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendMembershipRemindersHandler
{
    public function __construct(
        private readonly MembershipReminders $reminders,
    ) {
    }

    public function __invoke(SendMembershipReminders $message): void
    {
        $this->reminders->sendDue();
    }
}
