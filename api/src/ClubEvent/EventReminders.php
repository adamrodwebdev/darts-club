<?php

namespace App\ClubEvent;

use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\ClubEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Uid\Uuid;

/** Rappels J-7 (tous les adhérents) et J-1 (inscrits), une seule fois chacun. */
final class EventReminders
{
    public function __construct(
        private readonly ClubEventRepository $events,
        private readonly MemberNotifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly GuestMailer $guests,
    ) {
    }

    /** @return int nombre de rappels envoyés */
    public function sendDue(): int
    {
        $now = $this->clock->now();
        $sent = 0;

        foreach ($this->events->startingWithinDays($now, 8) as $event) {
            if ($event->isDueForDayReminder($now)) {
                // J-1 prime : le rappel J-7 n'a plus de sens
                $event->markWeekReminderSent($now);
                $event->markDayReminderSent($now);
                $participants = $event->participants();
                $sent += $this->guests->notifyGuests($event, 'reminder');
                if ([] !== $participants) {
                    $this->send($event, 'event.reminder_day', Recipients::users(...array_map(static fn ($u): Uuid => $u->getId(), $participants)));
                    ++$sent;
                }
            } elseif ($event->isDueForWeekReminder($now)) {
                $event->markWeekReminderSent($now);
                $this->send($event, 'event.reminder_week', Recipients::allMembers());
                ++$sent;
            }
        }

        $this->em->flush();

        return $sent;
    }

    private function send(\App\Entity\ClubEvent $event, string $titleKey, Recipients $recipients): void
    {
        $this->notifier->notify(
            new TranslatedNotification(
                category: Category::Event,
                title: new TranslatableMessage($titleKey, ['%title%' => $event->getTitle()], 'notifications'),
                body: NotifyOnClubEventChanges::body($event),
                link: NotifyOnClubEventChanges::link($event),
            ),
            $recipients,
        );
    }
}
