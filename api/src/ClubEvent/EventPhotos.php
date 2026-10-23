<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\User;
use App\Media\ImageUpload;
use App\Media\InvalidImageException;
use App\Repository\EventPhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Galerie de l'archive : photos ajoutées une fois l'événement commencé, jusqu'à 200 par événement. */
final class EventPhotos
{
    public const MAX_PER_EVENT = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly EventPhotoFiles $files,
        private readonly EventPhotoRepository $photos,
    ) {
    }

    /**
     * Tout ou rien : si une photo de l'envoi est refusée (format, dimensions), les fichiers déjà
     * enregistrés pour les autres sont supprimés, l'envoi ne laisse rien derrière lui.
     *
     * @param list<UploadedFile> $files
     *
     * @return list<EventPhoto>
     */
    public function add(ClubEvent $event, User $uploader, array $files): array
    {
        $now = $this->clock->now();
        $event->assertArchiveOpen($now);
        if ($this->photos->countForEvent($event) + \count($files) > self::MAX_PER_EVENT) {
            throw new InvalidImageException('La galerie est limitée à %max% photos.', null, ['%max%' => self::MAX_PER_EVENT]);
        }
        $uploads = array_map(ImageUpload::from(...), $files);

        $added = [];
        $stored = [];
        try {
            foreach ($uploads as $upload) {
                $saved = $stored[] = $this->files->store($upload);
                $photo = new EventPhoto(
                    event: $event,
                    uploader: $uploader,
                    largeId: $saved->largeId,
                    thumbId: $saved->thumbId,
                    width: $saved->width,
                    height: $saved->height,
                    now: $now,
                );
                $this->em->persist($photo);
                $added[] = $photo;
            }
            $this->em->flush();
        } catch (\Throwable $e) {
            foreach ($stored as $saved) {
                $this->files->discard($saved);
            }
            throw $e;
        }

        return $added;
    }

    public function delete(EventPhoto $photo): void
    {
        $this->em->remove($photo);
        $this->em->flush();
        $this->files->delete($photo);
    }
}
