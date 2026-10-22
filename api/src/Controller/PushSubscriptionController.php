<?php

namespace App\Controller;

use App\Dto\PushSubscriptionInput;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Notification\Push\PushEndpoint;
use App\Notification\Push\PushSender;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/push', name: 'push_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class PushSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/public-key', name: 'public_key', methods: ['GET'])]
    public function publicKey(PushSender $sender): JsonResponse
    {
        return $this->json(['publicKey' => $sender->publicKey()]);
    }

    #[Route('/subscriptions', name: 'subscribe', methods: ['POST'])]
    public function subscribe(#[CurrentUser] User $user, #[MapRequestPayload] PushSubscriptionInput $input): Response
    {
        $endpoint = new PushEndpoint($input->endpoint, $input->keys['p256dh'], $input->keys['auth']);
        $existing = $this->subscriptions->findOneByEndpoint($endpoint->url);

        if (null === $existing) {
            $this->em->persist(new PushSubscription($user, $endpoint, $this->clock->now()));
        } else {
            $existing->reassign($user, $endpoint);
        }
        $this->em->flush();

        return new Response(status: Response::HTTP_CREATED);
    }

    #[Route('/subscriptions', name: 'unsubscribe', methods: ['DELETE'])]
    public function unsubscribe(#[CurrentUser] User $user, #[MapRequestPayload] PushSubscriptionInput $input): Response
    {
        $subscription = $this->subscriptions->findOneByEndpoint($input->endpoint);

        if (null !== $subscription && $subscription->getUser()->getId()->equals($user->getId())) {
            $this->em->remove($subscription);
            $this->em->flush();
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
