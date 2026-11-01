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
     * Tous les topics privés d'un adhérent, en motif d'URL (protocole Mercure 1.0 : « urlpattern »,
     * les gabarits d'URI de la version 0.x n'existent plus).
     *
     * @return array{urlpattern: list<string>}
     */
    public static function privateTopicsOf(User $user): array
    {
        return ['urlpattern' => [self::BASE.'/users/'.$user->getId()->toRfc4122().'/*']];
    }
}
