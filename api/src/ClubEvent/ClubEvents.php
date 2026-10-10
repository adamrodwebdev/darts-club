<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\EventPost;
use App\Entity\User;
use App\Media\ImageProcessor;
use App\Media\ImageStorage;
use App\Media\InvalidImageException;
use App\Messaging\MessageBody;
use App\Repository\EventPhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Cas d'usage du calendrier et des archives. */
final class ClubEvents
{
    public const MAX_PHOTOS_PER_EVENT = 200;
    public const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly ImageProcessor $images,
        private readonly ImageStorage $eventPhotoStorage,
        private readonly EventPhotoRepository $photos,
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function schedule(User $organizer, EventDetails $details): ClubEvent
    {
        $event = ClubEvent::schedule($organizer, $details, $this->now());
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function edit(ClubEvent $event, EventDetails $details): void
    {
        $event->edit($details, $this->now());
        $this->em->flush();
    }

    public function cancel(ClubEvent $event, string $reason): void
    {
        $event->cancel($reason);
        $this->em->flush();
    }

    public function register(ClubEvent $event, User $member): void
    {
        $this->em->persist($event->register($member, $this->now()));
        $this->em->flush();
    }

    public function unregister(ClubEvent $event, User $member): void
    {
        $event->unregister($member, $this->now());
        $this->em->flush();
    }

    public function writeSummary(ClubEvent $event, string $summary): void
    {
        $event->writeSummary($summary);
        $this->em->flush();
    }

    /**
     * Les photos sont réservées à l'archive : l'événement doit avoir commencé.
     *
     * @param list<UploadedFile> $files
     *
     * @return list<EventPhoto>
     */
    public function addPhotos(ClubEvent $event, User $uploader, array $files): array
    {
        $this->assertArchiveOpen($event);
        if ($this->photos->countForEvent($event) + \count($files) > self::MAX_PHOTOS_PER_EVENT) {
            throw new InvalidImageException(\sprintf('La galerie est limitée à %d photos.', self::MAX_PHOTOS_PER_EVENT));
        }

        $added = [];
        foreach ($files as $file) {
            if (!$file->isValid() || $file->getSize() > self::MAX_PHOTO_BYTES) {
                throw new InvalidImageException(\sprintf('« %s » : fichier invalide ou trop lourd (10 Mo maximum).', mb_substr($file->getClientOriginalName(), 0, 60)));
            }

            $processed = $this->images->photo($file->getPathname());
            $photo = new EventPhoto(
                $event,
                $uploader,
                $this->eventPhotoStorage->store($processed['large']),
                $this->eventPhotoStorage->store($processed['thumb']),
                $processed['width'],
                $processed['height'],
            );
            $this->em->persist($photo);
            $added[] = $photo;
        }
        $this->em->flush();

        return $added;
    }

    public function deletePhoto(EventPhoto $photo): void
    {
        $this->em->remove($photo);
        $this->em->flush();
        $this->eventPhotoStorage->delete($photo->largeId());
        $this->eventPhotoStorage->delete($photo->thumbId());
    }

    public function addPost(ClubEvent $event, User $author, string $text): EventPost
    {
        $this->assertArchiveOpen($event);

        try {
            $post = new EventPost($event, $author, MessageBody::fromString($text));
        } catch (\InvalidArgumentException $e) {
            throw new RegistrationException($e->getMessage(), $e);
        }

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    public function deletePost(EventPost $post): void
    {
        $this->em->remove($post);
        $this->em->flush();
    }

    private function assertArchiveOpen(ClubEvent $event): void
    {
        if ($event->isCancelled() || !$event->hasStarted($this->now())) {
            throw new RegistrationException('Photos et posts sont ouverts une fois l\'événement commencé.');
        }
    }
}
