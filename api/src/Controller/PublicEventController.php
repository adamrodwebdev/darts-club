<?php

namespace App\Controller;

use App\ClubEvent\GuestRequest;
use App\ClubEvent\GuestResponses;
use App\Entity\ClubEvent;
use App\Entity\GuestResponse;
use App\Media\ImageStorage;
use App\Preference\Language;
use App\Repository\ClubEventRepository;
use App\Security\RequestThrottle;
use App\Translation\Unprocessable;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Événements ouverts au public (routes PUBLIC_ACCESS, voir security.yaml).
 * Ne montre jamais l'adresse exacte ni l'identité des participants : seulement
 * la ville et des compteurs. Les réponses sans compte passent par un lien
 * personnel envoyé par email ; le jeton circule dans le corps des requêtes,
 * jamais dans l'URL (journaux, en-tête Referer).
 */
final class PublicEventController extends AbstractController
{
    private const SLUG = '[a-z0-9-]{3,140}';

    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly ClubEventRepository $events,
        private readonly GuestResponses $guests,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Événements ouverts au public (accueil, calendrier des visiteurs) : à venir du plus
     * proche au plus lointain, puis passés du plus récent au plus ancien (hors annulés).
     */
    #[Route('/api/public/events', name: 'public_event_list', methods: ['GET'], format: 'json')]
    public function list(): JsonResponse
    {
        $now = $this->clock->now();
        $view = fn (ClubEvent $e): array => $this->view($e);

        return $this->json([
            'items' => array_map($view, $this->events->upcomingPublic($now)),
            'past' => array_map($view, $this->events->pastPublic($now)),
        ]);
    }

    /**
     * Lien adhérent (/evenements/{id}) ouvert sans compte : adresse de la page publique,
     * seulement si l'événement est public (sinon 404, comme un événement inexistant).
     */
    #[Route('/api/public/events/by-id/{id}', name: 'public_event_by_id', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'], format: 'json')]
    public function byId(string $id): JsonResponse
    {
        $event = Uuid::isValid($id) ? $this->events->find(Uuid::fromString($id)) : null;
        if (null === $event || !$event->isPublic()) {
            throw new NotFoundHttpException();
        }

        return $this->json(['slug' => $event->getSlug()]);
    }

    #[Route('/api/public/events/{slug}', name: 'public_event_show', requirements: ['slug' => self::SLUG], methods: ['GET'], format: 'json')]
    public function show(string $slug): JsonResponse
    {
        return $this->json($this->view($this->publicEvent($slug)));
    }

    #[Route('/api/public/events/{slug}/poster/{file}', name: 'public_event_poster', requirements: ['slug' => self::SLUG, 'file' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function poster(string $slug, string $file, ImageStorage $eventPosterStorage): BinaryFileResponse
    {
        $event = $this->publicEvent($slug);
        $path = true === $event->poster()?->has($file) ? $eventPosterStorage->path($file) : null;
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPublic();
        $response->setMaxAge(365 * 86400);
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }

    /** Corps : {name, email, answer: "je-viens" | "pas-sur", consent: true, website: "" (piège à robots)} */
    #[Route('/api/public/events/{slug}/responses', name: 'public_event_respond', requirements: ['slug' => self::SLUG], methods: ['POST'], format: 'json')]
    public function respond(
        string $slug,
        Request $request,
        RateLimiterFactoryInterface $guestResponseIpLimiter,
        RateLimiterFactoryInterface $guestResponseEmailLimiter,
    ): JsonResponse {
        $event = $this->publicEvent($slug);
        $data = $request->toArray();
        $accepted = $this->json(['status' => 'pending'], Response::HTTP_ACCEPTED);

        // Champ invisible rempli : robot, réponse identique mais rien n'est fait
        if ('' !== self::text($data, 'website')) {
            return $accepted;
        }
        if (true !== ($data['consent'] ?? null)) {
            throw new Unprocessable('Ton accord est nécessaire pour enregistrer ta réponse.');
        }

        $this->requestThrottle->consume($guestResponseIpLimiter, 'ip-'.$request->getClientIp());
        $this->requestThrottle->consume($guestResponseEmailLimiter, 'email-'.mb_strtolower(trim(self::text($data, 'email'))));

        $this->guard(fn () => $this->guests->request($event, new GuestRequest(
            name: DisplayName::fromString(self::text($data, 'name')),
            email: EmailAddress::fromString(self::text($data, 'email')),
            answer: ClubEventController::attendance($data['answer'] ?? null),
            language: Language::fromLocale($request->getLocale()),
        )));

        return $accepted;
    }

    /** Corps : {token} → réponse et événement (adresse complète une fois la réponse confirmée) */
    #[Route('/api/public/responses/lookup', name: 'public_response_lookup', methods: ['POST'], format: 'json')]
    public function lookup(Request $request, RateLimiterFactoryInterface $guestTokenLimiter): JsonResponse
    {
        return $this->json($this->responseView($this->response($request, $guestTokenLimiter)));
    }

    #[Route('/api/public/responses/confirm', name: 'public_response_confirm', methods: ['POST'], format: 'json')]
    public function confirm(Request $request, RateLimiterFactoryInterface $guestTokenLimiter): JsonResponse
    {
        $response = $this->response($request, $guestTokenLimiter);
        $this->guard(fn () => $this->guests->confirm($response));

        return $this->json($this->responseView($response));
    }

    /** Corps : {token, answer} */
    #[Route('/api/public/responses/answer', name: 'public_response_answer', methods: ['POST'], format: 'json')]
    public function answer(Request $request, RateLimiterFactoryInterface $guestTokenLimiter): JsonResponse
    {
        $response = $this->response($request, $guestTokenLimiter);
        $answer = ClubEventController::attendance($request->toArray()['answer'] ?? null);
        $this->guard(fn () => $this->guests->change($response, $answer));

        return $this->json($this->responseView($response));
    }

    #[Route('/api/public/responses/cancel', name: 'public_response_cancel', methods: ['POST'], format: 'json')]
    public function cancel(Request $request, RateLimiterFactoryInterface $guestTokenLimiter): Response
    {
        $response = $this->response($request, $guestTokenLimiter);
        $this->guard(fn () => $this->guests->cancel($response));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function publicEvent(string $slug): ClubEvent
    {
        $event = $this->events->findOneBy(['slug' => $slug]);
        if (null === $event || !$event->isPublic()) {
            throw new NotFoundHttpException();
        }

        return $event;
    }

    private function response(Request $request, RateLimiterFactoryInterface $limiter): GuestResponse
    {
        $this->requestThrottle->consume($limiter, 'ip-'.$request->getClientIp());
        $response = $this->guests->find(self::text($request->toArray(), 'token'));
        if (null === $response || !$response->getEvent()->isPublic()) {
            throw new NotFoundHttpException();
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function view(ClubEvent $event): array
    {
        $now = $this->clock->now();

        return [
            'slug' => $event->getSlug(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'city' => $event->getCity(),
            'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
            'endsAt' => $event->getEndsAt()->format(\DATE_ATOM),
            'status' => $event->getStatus()->value,
            'cancelReason' => $event->getCancelReason(),
            'capacity' => $event->getCapacity(),
            'going' => $event->goingCount(),
            'maybe' => $event->maybeCount(),
            'full' => $event->isFull(),
            'started' => $event->hasStarted($now),
            'past' => $event->isPast($now),
            'poster' => $event->poster()?->toPublicArray((string) $event->getSlug()),
        ];
    }

    /** @return array<string, mixed> */
    private function responseView(GuestResponse $response): array
    {
        $event = $response->getEvent();

        return [
            'name' => $response->name(),
            'answer' => $response->answer()?->value,
            'pendingAnswer' => $response->pendingAnswer()?->value,
            'confirmed' => $response->isConfirmed(),
            'expired' => $response->isConfirmationExpired($this->clock->now()),
            'event' => $this->view($event) + ['location' => $response->isConfirmed() ? $event->getLocation() : null],
        ];
    }

    /** @param array<mixed> $data */
    private static function text(array $data, string $key): string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : '';
    }

    private function guard(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException|\LogicException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
