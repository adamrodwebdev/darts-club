<?php

namespace App\Message;

final class SendPasswordResetEmail
{
    public function __construct(
        public readonly string $userId,
    ) {
    }
}
