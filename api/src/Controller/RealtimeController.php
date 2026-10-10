<?php

namespace App\Controller;

use App\Entity\User;
use App\Notification\RealtimeTopics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/realtime', name: 'realtime_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class RealtimeController extends AbstractController
{
    /**
     * Pose le cookie d'autorisation Mercure (HttpOnly) limité aux topics
     * privés de l'adhérent et renvoie l'URL du hub.
     */
    #[Route('/authorization', name: 'authorization', methods: ['POST'])]
    public function authorize(#[CurrentUser] User $user, Request $request, Authorization $authorization, HubInterface $hub): JsonResponse
    {
        $authorization->setCookie($request, RealtimeTopics::privateTopicsOf($user));

        return $this->json([
            'hub' => $hub->getPublicUrl(),
            'topics' => [RealtimeTopics::notifications($user)],
        ]);
    }
}
