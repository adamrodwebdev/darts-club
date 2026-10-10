<?php

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Membership\Season;
use App\Membership\TreasuryView;
use App\Repository\MembershipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MyMembershipController extends AbstractController
{
    #[Route('/api/me/membership', name: 'my_membership', methods: ['GET'], format: 'json')]
    public function __invoke(#[CurrentUser] User $user, MembershipRepository $memberships, ClockInterface $clock): JsonResponse
    {
        $now = $clock->now();
        $season = Season::containing($now);
        $history = $memberships->historyOf($user);
        $current = array_values(array_filter($history, static fn (Membership $m): bool => $m->season()->equals($season)))[0] ?? null;

        return $this->json([
            'season' => $season->label(),
            'current' => null !== $current ? TreasuryView::membership($current, false, $now) : null,
            'history' => array_map(static fn (Membership $m): array => TreasuryView::membership($m, false, $now), $history),
        ]);
    }
}
