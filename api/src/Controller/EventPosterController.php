<?php

namespace App\Controller;

use App\ClubEvent\EventPosters;
use App\Entity\ClubEvent;
use App\Entity\User;
use App\Media\ImageStorage;
use App\Media\InvalidImageException;
use App\Security\RequestThrottle;
use App\Security\Voter\ClubEventVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Affiche d'un événement : pose, retrait et fichiers. */
#[IsGranted('ROLE_MEMBER')]
final class EventPosterController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly EventPosters $posters,
    ) {
    }

    /** Multipart, champ « poster » ; remplace l'affiche existante. */
    #[Route('/api/events/{id}/poster', name: 'events_poster_set', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function set(ClubEvent $event, Request $request, #[CurrentUser] User $user, RateLimiterFactoryInterface $photoUploadLimiter): JsonResponse
    {
        $file = $request->files->get('poster');
        if (!$file instanceof UploadedFile) {
            throw new InvalidImageException('Joins une image (champ « poster »).');
        }

        $this->requestThrottle->consume($photoUploadLimiter, $user->getId()->toRfc4122());

        $poster = DomainCall::run(fn () => $this->posters->attach($event, $file));

        return $this->json($poster->toArray($event->getId()->toRfc4122()), Response::HTTP_CREATED);
    }

    #[Route('/api/events/{id}/poster', name: 'events_poster_delete', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function delete(ClubEvent $event): Response
    {
        $this->posters->remove($event);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Fichier d'affiche : son identifiant fait partie de l'URL, d'où un cache long. */
    #[Route('/api/events/{id}/poster/{file}', name: 'events_poster_file', requirements: ['id' => self::UUID, 'file' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function posterFile(ClubEvent $event, string $file, ImageStorage $eventPosterStorage): BinaryFileResponse
    {
        $path = true === $event->poster()?->has($file) ? $eventPosterStorage->path($file) : null;
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPrivate();
        $response->setMaxAge(365 * 86400);
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }
}
