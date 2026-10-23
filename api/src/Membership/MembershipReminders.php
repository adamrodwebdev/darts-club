<?php

namespace App\Membership;

use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\MembershipRepository;
use App\Translation\LocalizedDate;
use App\Translation\LocalizedMoney;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Translation\TranslatableMessage;

/** Relance des cotisations en retard (au plus une fois par semaine et par adhérent). */
final class MembershipReminders
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly MemberNotifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return int nombre d'adhérents relancés */
    public function sendDue(): int
    {
        $now = $this->clock->now();
        $season = Season::containing($now);
        $sent = 0;

        foreach ($this->memberships->forSeason($season) as $membership) {
            $user = $membership->getUser();
            if (null === $user || !$membership->shouldBeReminded($now)) {
                continue;
            }

            $this->notifier->notify(
                new TranslatedNotification(
                    category: Category::Membership,
                    title: new TranslatableMessage('membership.reminder_title', ['%season%' => (string) $season], 'notifications'),
                    body: new TranslatableMessage('membership.reminder_body', [
                        '%amount%' => new LocalizedMoney($membership->remaining()),
                        '%date%' => new LocalizedDate($season->dueDate(), withTime: false),
                    ], 'notifications'),
                    link: '/compte/cotisation',
                ),
                Recipients::users($user->getId()),
            );
            $membership->markReminded($now);
            ++$sent;
        }

        $this->em->flush();

        return $sent;
    }
}
