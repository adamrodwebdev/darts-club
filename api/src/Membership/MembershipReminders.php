<?php

namespace App\Membership;

use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\MembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

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
                new NotificationContent(
                    Category::Membership,
                    \sprintf('Cotisation %s en attente', $season),
                    \sprintf('Il reste %s à régler (échéance du %s). Contacte la trésorerie pour toute question.', $membership->remaining(), $season->dueDate()->format('d/m/Y')),
                    '/compte/cotisation',
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
