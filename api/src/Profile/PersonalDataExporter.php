<?php

namespace App\Profile;

use App\Entity\Notification;
use App\Entity\OAuthIdentity;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\PushSubscriptionRepository;
use Symfony\Component\Clock\ClockInterface;

/** Droit d'accès et à la portabilité (RGPD art. 15 et 20). */
final class PersonalDataExporter
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly OAuthIdentityRepository $identities,
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        return [
            'exportedAt' => $this->clock->now()->format(\DATE_ATOM),
            'account' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'displayName' => $user->displayName()->value,
                'phone' => $user->getPhone(),
                'roles' => $user->getRoles(),
                'emailVerified' => $user->isVerified(),
                'hasAvatar' => null !== $user->avatar(),
                'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
            ],
            'notificationPreferences' => $user->notificationPreferences()->toArray(),
            'linkedAccounts' => array_map(
                static fn (OAuthIdentity $i): array => ['provider' => $i->getProvider(), 'linkedAt' => $i->getCreatedAt()->format(\DATE_ATOM)],
                $this->identities->findBy(['user' => $user]),
            ),
            'pushSubscriptions' => array_map(
                static fn (PushSubscription $s): array => [
                    'service' => $s->endpoint()->service(),
                    'createdAt' => $s->getCreatedAt()->format(\DATE_ATOM),
                ],
                $this->subscriptions->findByUser($user),
            ),
            'notifications' => array_map(
                static fn (Notification $n): array => $n->toArray(),
                $this->notifications->latestFor($user, limit: 10_000),
            ),
        ];
    }
}
