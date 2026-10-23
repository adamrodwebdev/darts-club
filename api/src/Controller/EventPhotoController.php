<?php

namespace App\Controller;

use App\ClubEvent\EventPhotos;
use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
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

/** Galerie de l'archive d'un événement : envoi, suppression et fichiers des photos. */
#[IsGranted('ROLE_MEMBER')]
final class EventPhotoController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly EventPhotos $photos,
    ) {
    }

    #[Route('/api/events/{id}/photos', name: 'events_photos_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    public function add(ClubEvent $event, Request $request, #[CurrentUser] User $user, RateLimiterFactoryInterface $photoUploadLimiter): JsonResponse
    {
        $files = $request->files->get('photos');
        $files = array_values(array_filter(\is_array($files) ? $files : [$files], static fn (mixed $f): bool => $f instanceof UploadedFile));
        if ([] === $files || \count($files) > 10) {
            throw new InvalidImageException('Envoie entre 1 et 10 photos à la fois (champ « photos[] »).');
        }

        $this->requestThrottle->consume($photoUploadLimiter, $user->getId()->toRfc4122(), \count($files));

        $added = $this->photos->add($event, $user, $files);

        return $this->json(['items' => array_map(static fn (EventPhoto $p): array => $p->toArray(), $added)], Response::HTTP_CREATED);
    }

    #[Route('/api/event-photos/{id}', name: 'events_photos_delete', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(ClubEventVoter::DELETE_CONTENT, subject: 'photo')]
    public function delete(EventPhoto $photo): Response
    {
        $this->photos->delete($photo);

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
}
