<?php

namespace App\Controller;

use App\Dto\PushSubscriptionInput;
use App\Entity\User;
use App\Notification\Push\PushEndpoint;
use App\Notification\Push\PushSender;
use App\Notification\Push\PushSubscriptions;
use App\Security\RequestThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/push', name: 'push_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class PushSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly PushSubscriptions $subscriptions,
        private readonly RequestThrottle $requestThrottle,
        private readonly RateLimiterFactoryInterface $pushSubscriptionLimiter,
    ) {
    }

    #[Route('/public-key', name: 'public_key', methods: ['GET'])]
    public function publicKey(PushSender $sender): JsonResponse
    {
        return $this->json(['publicKey' => $sender->publicKey()]);
    }

    #[Route('/subscriptions', name: 'subscribe', methods: ['POST'])]
    public function subscribe(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] PushSubscriptionInput $input): Response
    {
        $this->requestThrottle->consume($this->pushSubscriptionLimiter, $user->getId()->toRfc4122());

        $endpoint = DomainCall::run(static fn () => new PushEndpoint($input->endpoint, $input->keys['p256dh'], $input->keys['auth']));
        $this->subscriptions->subscribe($user, $endpoint);

        return new Response(status: Response::HTTP_CREATED);
    }

    #[Route('/subscriptions', name: 'unsubscribe', methods: ['DELETE'])]
    public function unsubscribe(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] PushSubscriptionInput $input): Response
    {
        $this->subscriptions->unsubscribe($user, $input->endpoint);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
