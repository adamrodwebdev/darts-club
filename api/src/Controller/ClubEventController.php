<?php

namespace App\Controller;

use App\ClubEvent\ArchiveSearch;
use App\ClubEvent\Attendance;
use App\ClubEvent\ClubEvents;
use App\ClubEvent\ClubEventView;
use App\ClubEvent\EventDetails;
use App\ClubEvent\IcsCalendar;
use App\Entity\ClubEvent;
use App\Entity\User;
use App\Membership\Season;
use App\Repository\ClubEventRepository;
use App\Security\Voter\ClubEventVoter;
use App\Translation\InvalidValue;
use App\Translation\Unprocessable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class ClubEventController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly ClubEvents $events,
        private readonly ClubEventView $view,
    ) {
    }

    /** ?period=upcoming (défaut) | past, &season=2026-2027, &q=recherche (archives) */
    #[Route('/api/events', name: 'events_list', methods: ['GET'], format: 'json')]
    public function list(
        #[CurrentUser] User $user,
        ClubEventRepository $repository,
        #[MapQueryParameter] string $period = 'upcoming',
        #[MapQueryParameter] ?string $season = null,
        #[MapQueryParameter] string $q = '',
    ): JsonResponse {
        $now = $this->events->now();

        try {
            $items = 'past' === $period
                ? $repository->past($now, ArchiveSearch::fromQuery($season, $q))
                : $repository->upcoming($now);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json([
            'items' => array_map(fn (ClubEvent $e): array => $this->view->summary($e, $user), $items),
            'canManage' => $this->isGranted(ClubEventVoter::MANAGE),
        ]);
    }

    #[Route('/api/events/{id}', name: 'events_show', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    public function show(ClubEvent $event, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->view->detail($event, $user, $this->isGranted(ClubEventVoter::MANAGE, $event)));
    }

    #[Route('/api/events', name: 'events_create', methods: ['POST'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE)]
    public function create(Request $request, #[CurrentUser] User $organizer): JsonResponse
    {
        $event = DomainCall::run(fn (): ClubEvent => $this->events->schedule($organizer, self::details($request)));

        return $this->json($this->view->summary($event, $organizer), Response::HTTP_CREATED);
    }

    #[Route('/api/events/{id}', name: 'events_edit', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function edit(ClubEvent $event, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        DomainCall::run(fn () => $this->events->edit($event, self::details($request)));

        return $this->json($this->view->summary($event, $user));
    }

    #[Route('/api/events/{id}/cancel', name: 'events_cancel', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function cancel(ClubEvent $event, Request $request): Response
    {
        $reason = $request->toArray()['reason'] ?? '';
        DomainCall::run(fn () => $this->events->cancel($event, \is_string($reason) ? $reason : ''));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/events/{id}/summary', name: 'events_summary', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function summary(ClubEvent $event, Request $request): Response
    {
        $summary = $request->toArray()['summary'] ?? '';
        DomainCall::run(fn () => $this->events->writeSummary($event, \is_string($summary) ? $summary : ''));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/events/{id}/registration', name: 'events_register', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    public function register(ClubEvent $event, Request $request, #[CurrentUser] User $user): Response
    {
        // Corps facultatif : {answer: "je-viens" | "pas-sur"} (défaut : je viens)
        $answer = '' !== $request->getContent() ? ($request->toArray()['answer'] ?? null) : null;
        $this->events->respond($event, $user, null === $answer ? Attendance::Going : self::attendance($answer));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/events/{id}/registration', name: 'events_unregister', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    public function unregister(ClubEvent $event, #[CurrentUser] User $user): Response
    {
        $this->events->unregister($event, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/events/{id}/calendar.ics', name: 'events_ics', requirements: ['id' => self::UUID], methods: ['GET'])]
    public function ics(ClubEvent $event, IcsCalendar $calendar, #[CurrentUser] User $user): Response
    {
        return new Response($calendar->render([$event], 'Skull Darts 71', $user->language()->value), headers: [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evenement-skull-darts-71.ics"',
        ]);
    }

    /**
     * Corps : {title, description, location, startsAt, endsAt (ISO 8601 avec décalage), capacity?, public?, city?}.
     */
    private static function details(Request $request): EventDetails
    {
        $data = $request->toArray();
        $text = static fn (string $key): string => \is_string($data[$key] ?? null) ? $data[$key] : '';
        $date = static function (string $key) use ($text): \DateTimeImmutable {
            $value = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $text($key))
                ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.vP', $text($key));
            if (false === $value) {
                throw new InvalidValue('Date « %field% » invalide (format ISO 8601 attendu).', ['%field%' => $key]);
            }

            return $value;
        };
        $capacity = $data['capacity'] ?? null;

        return new EventDetails(
            title: $text('title'),
            description: $text('description'),
            location: $text('location'),
            startsAt: $date('startsAt'),
            endsAt: $date('endsAt'),
            capacity: \is_int($capacity) ? $capacity : null,
            public: true === ($data['public'] ?? false),
            city: $text('city'),
        );
    }

    public static function attendance(mixed $value): Attendance
    {
        return (\is_string($value) ? Attendance::tryFrom($value) : null)
            ?? throw new Unprocessable('Réponse inconnue : « je-viens » ou « pas-sur » attendu.');
    }
}
