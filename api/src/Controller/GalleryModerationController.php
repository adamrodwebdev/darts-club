<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EventPhoto;
use App\Entity\EventVideo;
use App\Entity\User;
use App\Gallery\GalleryModeration;
use App\Security\Voter\EventGalleryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Demandes de retrait (droit à l'image) et remise en ligne par la présidence. */
#[IsGranted('ROLE_MEMBER')]
final class GalleryModerationController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly GalleryModeration $moderation,
    ) {
    }

    #[Route('/api/event-photos/{id}/removal-request', name: 'events_photos_removal', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventGalleryVoter::REQUEST_REMOVAL, subject: 'photo')]
    public function requestPhotoRemoval(EventPhoto $photo, #[CurrentUser] User $user): Response
    {
        $this->moderation->requestRemoval($photo, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/event-videos/{id}/removal-request', name: 'events_videos_removal', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventGalleryVoter::REQUEST_REMOVAL, subject: 'video')]
    public function requestVideoRemoval(EventVideo $video, #[CurrentUser] User $user): Response
    {
        $this->moderation->requestRemoval($video, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/event-photos/{id}/restore', name: 'events_photos_restore', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventGalleryVoter::MODERATE, subject: 'photo')]
    public function restorePhoto(EventPhoto $photo, #[CurrentUser] User $user): Response
    {
        $this->moderation->restore($photo, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/event-videos/{id}/restore', name: 'events_videos_restore', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventGalleryVoter::MODERATE, subject: 'video')]
    public function restoreVideo(EventVideo $video, #[CurrentUser] User $user): Response
    {
        $this->moderation->restore($video, $user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
