<?php

namespace App\Membership;

use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\MembershipRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyOnMembershipPaid
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly MemberNotifier $notifier,
    ) {
    }

    public function __invoke(MembershipPaid $event): void
    {
        $membership = $this->memberships->find($event->membershipId);
        $user = $membership?->getUser();
        if (null === $membership || null === $user) {
            return;
        }

        $this->notifier->notify(
            new NotificationContent(
                Category::Membership,
                \sprintf('Cotisation %s réglée', $membership->season()),
                \sprintf('Merci ! Ta cotisation de %s est enregistrée. Ton badge « Cotisant » est actif.', $membership->amountDue()),
                '/compte/cotisation',
            ),
            Recipients::users($user->getId()),
        );
    }
}
