<?php

declare(strict_types=1);

namespace App\Message;

final class SendVerificationEmail
{
    public function __construct(
        public readonly string $userId,
    ) {
    }
}
