<?php

namespace App\Membership;

use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\MembershipRepository;
use App\Translation\LocalizedMoney;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Translation\TranslatableMessage;

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
            new TranslatedNotification(
                Category::Membership,
                new TranslatableMessage('membership.paid_title', ['%season%' => (string) $membership->season()], 'notifications'),
                new TranslatableMessage('membership.paid_body', ['%amount%' => new LocalizedMoney($membership->amountDue())], 'notifications'),
                '/compte/cotisation',
            ),
            Recipients::users($user->getId()),
        );
    }
}
