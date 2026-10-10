<?php

namespace App\Controller;

use App\ClubEvent\IcsCalendar;
use App\Entity\User;
use App\Repository\ClubEventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Abonnement calendrier (Google Agenda, Apple Calendrier, Outlook…).
 * Les applications de calendrier n'ont pas de session : l'accès se fait par
 * un jeton secret personnel, révocable, dont seule l'empreinte est stockée.
 */
final class CalendarFeedController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $apiUrl,
    ) {
    }

    #[Route('/api/me/calendar-feed', name: 'calendar_feed_create', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_MEMBER')]
    public function create(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $token = $user->rotateCalendarToken();
        $em->flush();

        $url = rtrim($this->apiUrl, '/').'/api/calendar/'.$token.'.ics';

        return $this->json(['url' => $url, 'webcal' => preg_replace('#^https?://#', 'webcal://', $url)], Response::HTTP_CREATED);
    }

    #[Route('/api/me/calendar-feed', name: 'calendar_feed_revoke', methods: ['DELETE'])]
    #[IsGranted('ROLE_MEMBER')]
    public function revoke(#[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $user->revokeCalendarToken();
        $em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/calendar/{token}.ics', name: 'calendar_feed', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function feed(string $token, UserRepository $users, ClubEventRepository $events, IcsCalendar $calendar, ClockInterface $clock): Response
    {
        $user = $users->findOneByCalendarToken($token);
        if (null === $user || !$user->isActive()) {
            throw new NotFoundHttpException();
        }

        $now = $clock->now();

        return new Response($calendar->render($events->forCalendarFeed($now), 'Darts Club', $now), headers: [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Cache-Control' => 'private, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
