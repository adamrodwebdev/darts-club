<?php

namespace App\Notification\Push;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Abonnements Web Push des navigateurs d'un adhérent : dix au plus, le plus ancien laisse sa place. */
final class PushSubscriptions
{
    public const MAX_PER_USER = 10;

    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function subscribe(User $user, PushEndpoint $endpoint): void
    {
        $existing = $this->subscriptions->findOneByEndpoint($endpoint->url);
        if (null === $existing) {
            $this->makeRoomFor($user);
            $this->em->persist(new PushSubscription($user, $endpoint, $this->clock->now()));
        } else {
            // Même navigateur, autre compte : il rejoint les abonnements de celui-ci
            if (!$existing->getUser()->getId()->equals($user->getId())) {
                $this->makeRoomFor($user);
            }
            $existing->reassign($user, $endpoint);
        }

        $this->em->flush();
    }

    /** Seul le propriétaire de l'abonnement peut le retirer ; sinon, rien n'est fait. */
    public function unsubscribe(User $user, string $url): void
    {
        $subscription = $this->subscriptions->findOneByEndpoint($url);
        if (null === $subscription || !$subscription->getUser()->getId()->equals($user->getId())) {
            return;
        }

        $this->em->remove($subscription);
        $this->em->flush();
    }

    private function makeRoomFor(User $user): void
    {
        $owned = $this->subscriptions->findByUser($user);
        foreach (\array_slice($owned, 0, max(0, \count($owned) - self::MAX_PER_USER + 1)) as $oldest) {
            $this->em->remove($oldest);
        }
    }
}
