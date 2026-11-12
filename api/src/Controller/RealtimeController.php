<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Notification\RealtimeTopics;
use App\Repository\ClubEventRepository;
use App\Security\Voter\EveningServiceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/realtime', name: 'realtime_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class RealtimeController extends AbstractController
{
    /**
     * Pose le cookie d'autorisation Mercure (HttpOnly) limité aux topics privés de l'adhérent et,
     * avec `?event=`, à celui d'une soirée qu'il sert ou surveille ; renvoie l'URL du hub et les
     * topics à suivre.
     */
    #[Route('/authorization', name: 'authorization', methods: ['POST'])]
    public function authorize(#[CurrentUser] User $user, Request $request, Authorization $authorization, HubInterface $hub, ClubEventRepository $events): JsonResponse
    {
        $id = $request->query->getString('event');
        $evening = Uuid::isValid($id) ? $events->find($id) : null;
        if (null !== $evening && !$this->isGranted(EveningServiceVoter::WATCH, $evening)) {
            $evening = null;
        }

        $authorization->setCookie($request, RealtimeTopics::subscriptionsOf($user, $evening));

        return $this->json([
            'hub' => $hub->getPublicUrl(),
            'topics' => null === $evening ? [RealtimeTopics::notifications($user)] : [RealtimeTopics::notifications($user), RealtimeTopics::evening($evening)],
        ]);
    }
}
