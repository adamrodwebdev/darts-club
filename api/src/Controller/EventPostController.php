<?php

namespace App\Controller;

use App\ClubEvent\EventPosts;
use App\Entity\ClubEvent;
use App\Entity\EventPost;
use App\Entity\User;
use App\Security\RequestThrottle;
use App\Security\Voter\ClubEventVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Fil de l'archive d'un événement : publication et suppression des posts. */
#[IsGranted('ROLE_MEMBER')]
final class EventPostController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly EventPosts $posts,
    ) {
    }

    #[Route('/api/events/{id}/posts', name: 'events_posts_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    public function add(ClubEvent $event, Request $request, #[CurrentUser] User $user, RateLimiterFactoryInterface $messagePostLimiter): JsonResponse
    {
        $this->requestThrottle->consume($messagePostLimiter, $user->getId()->toRfc4122());

        $body = $request->toArray()['body'] ?? '';
        $post = $this->posts->add($event, $user, \is_string($body) ? $body : '');

        return $this->json(['id' => $post->getId()->toRfc4122()], Response::HTTP_CREATED);
    }

    #[Route('/api/event-posts/{id}', name: 'events_posts_delete', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(ClubEventVoter::DELETE_CONTENT, subject: 'post')]
    public function delete(EventPost $post, #[CurrentUser] User $user): Response
    {
        $this->posts->delete($post, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
