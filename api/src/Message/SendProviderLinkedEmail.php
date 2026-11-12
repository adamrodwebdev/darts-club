<?php

declare(strict_types=1);

namespace App\Message;

final class SendProviderLinkedEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $provider,
    ) {
    }
}
