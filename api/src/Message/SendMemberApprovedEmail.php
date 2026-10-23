<?php

namespace App\Message;

final class SendMemberApprovedEmail
{
    public function __construct(
        public readonly string $userId,
    ) {
    }
}
