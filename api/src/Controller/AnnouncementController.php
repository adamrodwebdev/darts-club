<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Repository\AnnouncementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Canal d'informations, lisible par tous les adhérents connectés. */
#[IsGranted('ROLE_MEMBER')]
final class AnnouncementController extends AbstractController
{
    #[Route('/api/announcements', name: 'announcements_feed', methods: ['GET'], format: 'json')]
    public function __invoke(AnnouncementRepository $announcements): JsonResponse
    {
        return $this->json(['items' => array_map(static fn (Announcement $a): array => $a->toArray(), $announcements->feed())]);
    }
}
