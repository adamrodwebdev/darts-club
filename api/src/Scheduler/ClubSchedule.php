<?php

namespace App\Scheduler;

use App\ClubEvent\PurgeAudience;
use App\ClubEvent\PurgeGuestResponses;
use App\ClubEvent\SendEventReminders;
use App\Membership\SendMembershipReminders;
use App\Security\PurgeUnverifiedAccounts;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/** Tâches planifiées du club (worker : messenger:consume scheduler_default). */
#[AsSchedule]
final class ClubSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // Après un arrêt du worker, la dernière exécution manquée est rattrapée
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                // Relance des cotisations : chaque lundi à 10 h
                RecurringMessage::cron('0 10 * * 1', new SendMembershipReminders()),
                // Rappels J-7 / J-1 des événements : toutes les heures
                RecurringMessage::cron('5 * * * *', new SendEventReminders()),
                // Réponses sans compte : conservation limitée, chaque nuit à 4 h 20
                RecurringMessage::cron('20 4 * * *', new PurgeGuestResponses()),
                // Mesure d'audience : clés détruites, détail résumé (4 h 30)
                RecurringMessage::cron('30 4 * * *', new PurgeAudience()),
                // Comptes jamais confirmés depuis 7 jours (4 h 40)
                RecurringMessage::cron('40 4 * * *', new PurgeUnverifiedAccounts()),
            );
    }
}
