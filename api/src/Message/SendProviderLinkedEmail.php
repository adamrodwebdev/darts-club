<?php

namespace App\Message;

final class SendProviderLinkedEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $provider,
    ) {
    }
}
