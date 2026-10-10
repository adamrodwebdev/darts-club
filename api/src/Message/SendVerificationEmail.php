<?php

namespace App\Message;

final class SendVerificationEmail
{
    public function __construct(
        public readonly string $userId,
    ) {
    }
}
