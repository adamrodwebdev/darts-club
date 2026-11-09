<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\VideoUploadInput;
use App\Entity\ClubEvent;
use App\Entity\EventVideo;
use App\Entity\User;
use App\Gallery\VideoUploads;
use App\Media\ImageFiles;
use App\Media\NegotiatedImage;
use App\Media\VideoStorage;
use App\Security\RequestThrottle;
use App\Security\Voter\ClubEventVoter;
use App\Security\Voter\EventGalleryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Vidéos de la galerie : envoi par morceaux, état de la conversion, lecture, suppression. */
#[IsGranted('ROLE_MEMBER')]
final class EventVideoController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly VideoUploads $uploads,
    ) {
    }

    #[Route('/api/events/{id}/videos', name: 'events_videos_start', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventGalleryVoter::ADD_VIDEOS, subject: 'event')]
    public function start(ClubEvent $event, #[MapRequestPayload] VideoUploadInput $input, #[CurrentUser] User $user, RateLimiterFactoryInterface $videoUploadLimiter): JsonResponse
    {
        $this->requestThrottle->consume($videoUploadLimiter, $user->getId()->toRfc4122());
        $video = $this->uploads->start($event, $user, $input->toDeclaration());

        return $this->json($video->toArray() + ['chunkSize' => VideoUploads::CHUNK_BYTES], Response::HTTP_CREATED);
    }

    /** Corps brut (application/octet-stream) : le morceau qui commence à l'octet {offset} */
    #[Route('/api/event-videos/{id}/chunks/{offset}', name: 'events_videos_chunk', requirements: ['id' => self::UUID, 'offset' => '\d{1,10}'], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventGalleryVoter::SEND, subject: 'video')]
    public function chunk(EventVideo $video, int $offset, Request $request, #[CurrentUser] User $user, RateLimiterFactoryInterface $videoChunkLimiter): JsonResponse
    {
        $this->requestThrottle->consume($videoChunkLimiter, $user->getId()->toRfc4122());
        $this->uploads->append($video, $offset, $request->getContent(true));

        return $this->json($video->toArray());
    }

    /** Position de l'envoi (reprise après une coupure) et avancement de la conversion */
    #[Route('/api/event-videos/{id}', name: 'events_videos_show', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EventGalleryVoter::VIEW, subject: 'video')]
    public function show(EventVideo $video): JsonResponse
    {
        return $this->json($video->toArray());
    }

    #[Route('/api/event-videos/{id}', name: 'events_videos_delete', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(ClubEventVoter::DELETE_CONTENT, subject: 'video')]
    public function delete(EventVideo $video, #[CurrentUser] User $user): Response
    {
        $this->uploads->delete($video, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Lecture avec reprise (plages d'octets, exigées par Safari sur iPhone) */
    #[Route('/api/event-videos/{id}/file/{codec}', name: 'events_videos_file', requirements: ['id' => self::UUID, 'codec' => 'h264|av1'], defaults: ['codec' => 'h264'], methods: ['GET'])]
    #[IsGranted(EventGalleryVoter::VIEW, subject: 'video')]
    public function videoFile(EventVideo $video, string $codec, VideoStorage $videoStorage, Request $request): BinaryFileResponse
    {
        $id = 'av1' === $codec ? $video->av1FileId() : $video->fileId();
        $path = null === $id ? null : $videoStorage->path($id);
        if (null === $id || null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'video/mp4'], autoEtag: false);
        $response->setPrivate();
        $response->setMaxAge(30 * 86400);
        $response->setEtag($id);
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/api/event-videos/{id}/poster', name: 'events_videos_poster', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(EventGalleryVoter::VIEW, subject: 'video')]
    public function poster(EventVideo $video, VideoStorage $videoStorage, Request $request): BinaryFileResponse
    {
        $webp = null === $video->posterId() ? null : $videoStorage->path($video->posterId());
        if (null === $webp) {
            throw new NotFoundHttpException();
        }
        $avif = null === $video->posterAvifId() ? null : $videoStorage->path($video->posterAvifId());

        return NegotiatedImage::response($request, new ImageFiles($webp, $avif, (string) $video->posterId()));
    }
}
