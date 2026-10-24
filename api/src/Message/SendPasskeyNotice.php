<?php

namespace App\Message;

final class SendPasskeyNotice
{
    public const ADDED = 'added';
    public const RESET = 'reset';

    /** @param self::ADDED|self::RESET $notice */
    public function __construct(
        public readonly string $userId,
        public readonly string $notice,
    ) {
    }
}
