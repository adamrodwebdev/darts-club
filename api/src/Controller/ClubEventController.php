<?php

namespace App\Controller;

use App\ClubEvent\ClubEvents;
use App\ClubEvent\ClubEventView;
use App\ClubEvent\EventDetails;
use App\ClubEvent\IcsCalendar;
use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\EventPost;
use App\Entity\User;
use App\Media\ImageStorage;
use App\Media\InvalidImageException;
use App\Membership\Season;
use App\Repository\ClubEventRepository;
use App\Repository\EventPhotoRepository;
use App\Repository\EventPostRepository;
use App\Security\Voter\ClubEventVoter;
use App\Translation\InvalidValue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
                ? $repository->past($now, null !== $season && '' !== $season ? Season::fromLabel($season) : null, mb_substr($q, 0, 80))
                : $repository->upcoming($now);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json([
            'items' => array_map(fn (ClubEvent $e): array => $this->view->summary($e, $user, $now), $items),
            'canManage' => $this->isGranted(ClubEventVoter::MANAGE),
        ]);
    }

    #[Route('/api/events/{id}', name: 'events_show', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    public function show(ClubEvent $event, #[CurrentUser] User $user, EventPhotoRepository $photos, EventPostRepository $posts): JsonResponse
    {
        return $this->json($this->view->detail(
            $event,
            $user,
            $this->events->now(),
            $photos->forEvent($event),
            $posts->forEvent($event),
            $this->isGranted(ClubEventVoter::MANAGE, $event),
        ));
    }

    #[Route('/api/events', name: 'events_create', methods: ['POST'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE)]
    public function create(Request $request, #[CurrentUser] User $organizer): JsonResponse
    {
        $event = $this->guard(fn (): ClubEvent => $this->events->schedule($organizer, self::details($request)));

        return $this->json($this->view->summary($event, $organizer, $this->events->now()), Response::HTTP_CREATED);
    }

    #[Route('/api/events/{id}', name: 'events_edit', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function edit(ClubEvent $event, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->guard(fn () => $this->events->edit($event, self::details($request)));

        return $this->json($this->view->summary($event, $user, $this->events->now()));
    }

    #[Route('/api/events/{id}/cancel', name: 'events_cancel', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function cancel(ClubEvent $event, Request $request): Response
    {
        $reason = $request->toArray()['reason'] ?? '';
        $this->guard(fn () => $this->events->cancel($event, \is_string($reason) ? $reason : ''));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/events/{id}/summary', name: 'events_summary', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function summary(ClubEvent $event, Request $request): Response
    {
        $summary = $request->toArray()['summary'] ?? '';
        $this->guard(fn () => $this->events->writeSummary($event, \is_string($summary) ? $summary : ''));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/events/{id}/registration', name: 'events_register', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    public function register(ClubEvent $event, #[CurrentUser] User $user): Response
    {
        $this->events->register($event, $user);

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
        return new Response($calendar->render([$event], 'Skull Darts 71', $this->events->now(), $user->language()->value), headers: [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evenement-skull-darts-71.ics"',
        ]);
    }

    #[Route('/api/events/{id}/photos', name: 'events_photos_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    public function addPhotos(ClubEvent $event, Request $request, #[CurrentUser] User $user, RateLimiterFactoryInterface $photoUploadLimiter): JsonResponse
    {
        $files = $request->files->get('photos');
        $files = array_values(array_filter(\is_array($files) ? $files : [$files], static fn (mixed $f): bool => $f instanceof UploadedFile));
        if ([] === $files || \count($files) > 10) {
            throw new InvalidImageException('Envoie entre 1 et 10 photos à la fois (champ « photos[] »).');
        }

        $limit = $photoUploadLimiter->create($user->getId()->toRfc4122())->consume(\count($files));
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $added = $this->events->addPhotos($event, $user, $files);

        return $this->json(['items' => array_map(static fn (EventPhoto $p): array => $p->toArray(), $added)], Response::HTTP_CREATED);
    }

    #[Route('/api/event-photos/{id}', name: 'events_photos_delete', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(ClubEventVoter::DELETE_CONTENT, subject: 'photo')]
    public function deletePhoto(EventPhoto $photo): Response
    {
        $this->events->deletePhoto($photo);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/event-photos/{id}/{size}', name: 'events_photos_file', requirements: ['id' => self::UUID, 'size' => 'thumb|large'], methods: ['GET'])]
    public function photoFile(EventPhoto $photo, string $size, ImageStorage $eventPhotoStorage, Request $request): BinaryFileResponse
    {
        $id = 'thumb' === $size ? $photo->thumbId() : $photo->largeId();
        $path = $eventPhotoStorage->path($id);
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPrivate();
        $response->setMaxAge(30 * 86400);
        $response->setEtag($id);
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/api/events/{id}/posts', name: 'events_posts_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    public function addPost(ClubEvent $event, Request $request, #[CurrentUser] User $user, RateLimiterFactoryInterface $messagePostLimiter): JsonResponse
    {
        $limit = $messagePostLimiter->create($user->getId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $body = $request->toArray()['body'] ?? '';
        $post = $this->events->addPost($event, $user, \is_string($body) ? $body : '');

        return $this->json(['id' => $post->getId()->toRfc4122()], Response::HTTP_CREATED);
    }

    #[Route('/api/event-posts/{id}', name: 'events_posts_delete', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(ClubEventVoter::DELETE_CONTENT, subject: 'post')]
    public function deletePost(EventPost $post): Response
    {
        $this->events->deletePost($post);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Corps : {title, description, location, startsAt, endsAt (ISO 8601 avec décalage), capacity?}.
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
            $text('title'),
            $text('description'),
            $text('location'),
            $date('startsAt'),
            $date('endsAt'),
            \is_int($capacity) ? $capacity : null,
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    private function guard(callable $action): mixed
    {
        try {
            return $action();
        } catch (\InvalidArgumentException|\LogicException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
