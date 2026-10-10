<?php

namespace App\Membership;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/** Tâches planifiées de la trésorerie (worker : messenger:consume scheduler_membership). */
#[AsSchedule('membership')]
final class MembershipSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            // Chaque lundi à 10 h (heure du serveur)
            RecurringMessage::cron('0 10 * * 1', new SendMembershipReminders()),
        );
    }
}
