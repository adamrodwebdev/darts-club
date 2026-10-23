<?php

namespace App\Controller;

use App\ClubEvent\EventAudience;
use App\ClubEvent\PageVisit;
use App\Entity\ClubEvent;
use App\Entity\User;
use App\Repository\ClubEventRepository;
use App\Security\ClientNetwork;
use App\Security\Voter\ClubEventVoter;
use App\Security\Voter\EventAudienceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Audience des annonces d'événements : comptage des vues (page adhérent et
 * page publique) et statistiques réservées à l'administrateur.
 */
final class EventAudienceController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly EventAudience $audience,
    ) {
    }

    /** Vue de la page adhérent (envoyée par le navigateur après 2 s d'affichage) */
    #[Route('/api/events/{id}/view', name: 'events_view', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_MEMBER')]
    public function memberView(ClubEvent $event, Request $request, RateLimiterFactoryInterface $eventViewLimiter): Response
    {
        $this->record($event, $request, $eventViewLimiter);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Vue de la page publique : visiteur anonyme, ou adhérent connecté (reconnu par sa session) */
    #[Route('/api/public/events/{slug}/view', name: 'public_event_view', requirements: ['slug' => '[a-z0-9-]{3,140}'], methods: ['POST'], format: 'json')]
    public function publicView(string $slug, Request $request, RateLimiterFactoryInterface $eventViewLimiter, ClubEventRepository $events): Response
    {
        $event = $events->findOneBy(['slug' => $slug]);
        if (null !== $event && $event->isPublic()) {
            $this->record($event, $request, $eventViewLimiter);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/admin/events/{id}/audience', name: 'events_audience', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EventAudienceVoter::VIEW, subject: 'event')]
    public function report(ClubEvent $event): JsonResponse
    {
        return $this->json($this->audience->report($event));
    }

    #[Route('/api/admin/events/{id}/audience.csv', name: 'events_audience_csv', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(EventAudienceVoter::VIEW, subject: 'event')]
    public function csv(ClubEvent $event, TranslatorInterface $translator, Request $request): Response
    {
        $locale = $request->getLocale();
        $label = static fn (string $key): string => $translator->trans('audience.'.$key, [], 'exports', $locale);
        $lines = [[$label('day'), $label('members'), $label('visitors')]];
        foreach ($this->audience->report($event)['days'] as $day) {
            $lines[] = [$day['day'], (string) $day['members'], (string) $day['visitors']];
        }

        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Export impossible.');
        }
        foreach ($lines as $line) {
            fputcsv($handle, $line, ';', '"', '');
        }
        rewind($handle);
        $content = "\u{FEFF}".stream_get_contents($handle);
        fclose($handle);

        return new Response($content, headers: [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audience-'.$event->getId()->toRfc4122().'.csv"',
        ]);
    }

    private function record(ClubEvent $event, Request $request, RateLimiterFactoryInterface $limiter): void
    {
        // Au-delà de la limite : ignoré sans erreur (la mesure n'a pas à gêner la navigation)
        if (!$limiter->create('ip-'.ClientNetwork::of($request))->consume()->isAccepted()) {
            return;
        }

        $user = $this->getUser();
        // Les organisateurs ne faussent pas leurs propres statistiques
        if ($user instanceof User && $this->isGranted(ClubEventVoter::MANAGE, $event)) {
            return;
        }

        $this->audience->record($event, new PageVisit(
            member: $user instanceof User ? $user : null,
            ip: $request->getClientIp(),
            userAgent: (string) $request->headers->get('User-Agent', ''),
        ));
    }
}
