<?php

namespace App\Controller;

use App\Entity\User;
use App\Messaging\MessagingView;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Annuaire des adhérents pour choisir un destinataire (pseudo + avatar, jamais l'email). */
#[IsGranted('ROLE_MEMBER')]
final class MemberDirectoryController extends AbstractController
{
    #[Route('/api/members', name: 'members_list', methods: ['GET'], format: 'json')]
    public function __invoke(#[CurrentUser] User $viewer, UserRepository $users, MessagingView $view, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        $needle = mb_strtolower(trim($q));

        $members = array_filter(
            $users->findBy(['verified' => true]),
            static fn (User $u): bool => !$u->getId()->equals($viewer->getId())
                && ('' === $needle || str_contains(mb_strtolower($u->displayName()->value), $needle)),
        );
        usort($members, static fn (User $a, User $b): int => strcasecmp($a->displayName()->value, $b->displayName()->value));

        return $this->json(['items' => array_map($view->member(...), \array_slice($members, 0, 50))]);
    }
}
