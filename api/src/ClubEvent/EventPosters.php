<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Media\ImageProcessor;
use App\Media\ImageStorage;
use App\Media\ImageUpload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Affiche d'un événement (JPEG, PNG ou WebP) : ré-encodée en deux tailles ; la précédente est supprimée. */
final class EventPosters
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageProcessor $images,
        private readonly ImageStorage $eventPosterStorage,
    ) {
    }

    public function attach(ClubEvent $event, UploadedFile $file): Poster
    {
        $processed = $this->images->poster(ImageUpload::from($file)->path);
        $poster = new Poster(
            largeId: $this->eventPosterStorage->store($processed['large']),
            cardId: $this->eventPosterStorage->store($processed['card']),
            width: $processed['width'],
            height: $processed['height'],
        );

        $previous = $event->poster();
        try {
            $event->attachPoster($poster);
        } catch (\LogicException $e) {
            $this->deleteFiles($poster);
            throw $e;
        }
        $this->em->flush();
        $this->deleteFiles($previous);

        return $poster;
    }

    public function remove(ClubEvent $event): void
    {
        $previous = $event->poster();
        $event->removePoster();
        $this->em->flush();
        $this->deleteFiles($previous);
    }

    private function deleteFiles(?Poster $poster): void
    {
        if (null !== $poster) {
            $this->eventPosterStorage->delete($poster->largeId);
            $this->eventPosterStorage->delete($poster->cardId);
        }
    }
}
