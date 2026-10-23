<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Security\Voter\NotificationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', name: 'notifications_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, #[MapQueryParameter] bool $unread = false): JsonResponse
    {
        return $this->json([
            'items' => array_map(
                static fn (Notification $n): array => $n->toArray(),
                $this->notifications->latestFor($user, $unread),
            ),
            'unread' => $this->notifications->countUnread($user),
        ]);
    }

    #[Route('/{id}/read', name: 'read', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    #[IsGranted(NotificationVoter::OWNER, subject: 'notification')]
    public function read(Notification $notification, EntityManagerInterface $em): Response
    {
        $notification->markRead($this->clock->now());
        $em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function readAll(#[CurrentUser] User $user): Response
    {
        $this->notifications->markAllRead($user, $this->clock->now());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
