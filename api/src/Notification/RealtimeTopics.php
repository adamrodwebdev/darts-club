<?php

namespace App\Notification;

use App\Entity\User;

/** Topics Mercure de l'application (URI absolues, opaques pour le client). */
final class RealtimeTopics
{
    private const BASE = 'https://darts-club.app';

    public static function notifications(User $user): string
    {
        return self::BASE.'/users/'.$user->getId()->toRfc4122().'/notifications';
    }

    /**
     * Tous les topics privés d'un adhérent.
     *
     * @return list<string>
     */
    public static function privateTopicsOf(User $user): array
    {
        return [self::BASE.'/users/'.$user->getId()->toRfc4122().'/{+rest}'];
    }
}
