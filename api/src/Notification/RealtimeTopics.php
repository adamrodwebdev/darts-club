<?php

namespace App\Notification;

use App\Entity\ClubEvent;
use App\Entity\User;

/** Topics Mercure de l'application (URI absolues, opaques pour le client). */
final class RealtimeTopics
{
    private const BASE = 'https://darts-club.app';

    public static function notifications(User $user): string
    {
        return self::BASE.'/users/'.$user->getId()->toRfc4122().'/notifications';
    }

    /** Ce qui se passe pendant une soirée (ventes de l'accueil, commandes) : équipes et bureau seulement */
    public static function evening(ClubEvent $event): string
    {
        return self::BASE.'/evenings/'.$event->getId()->toRfc4122().'/service';
    }

    /**
     * Ce qu'un adhérent peut lire : tous ses topics privés, en motif d'URL (protocole Mercure 1.0 :
     * « urlpattern », les gabarits d'URI de la version 0.x n'existent plus), et le topic d'une
     * soirée qu'il sert ou surveille.
     *
     * @return array<string, list<string>> motifs par type de correspondance (urlpattern, exact)
     */
    public static function subscriptionsOf(User $user, ?ClubEvent $evening = null): array
    {
        $grants = ['urlpattern' => [self::BASE.'/users/'.$user->getId()->toRfc4122().'/*']];
        if (null !== $evening) {
            $grants['exact'] = [self::evening($evening)];
        }

        return $grants;
    }
}
