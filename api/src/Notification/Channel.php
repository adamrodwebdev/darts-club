<?php

namespace App\Notification;

enum Channel: string
{
    /** Centre de notifications + temps réel ; toujours actif */
    case InApp = 'in_app';
    case Email = 'email';
    case Push = 'push';

    /** @return list<self> */
    public static function optional(): array
    {
        return [self::Email, self::Push];
    }
}
