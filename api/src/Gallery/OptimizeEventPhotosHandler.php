<?php

declare(strict_types=1);

namespace App\Gallery;

use App\Media\AvifVariants;
use App\Media\ImageProcessor;
use App\Media\ImageStorage;
use App\Message\OptimizeEventPhotos;
use App\Repository\EventPhotoRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Versions AVIF des photos (grande et vignette), tirées du WebP déjà en ligne, dans le worker :
 * l'envoi reste rapide, la galerie s'allège quelques secondes plus tard.
 */
#[AsMessageHandler]
final class OptimizeEventPhotosHandler
{
    public function __construct(
        private readonly EventPhotoRepository $photos,
        private readonly ImageStorage $eventPhotoStorage,
        private readonly AvifVariants $eventPhotoVariants,
        private readonly ImageProcessor $images,
    ) {
    }

    public function __invoke(OptimizeEventPhotos $message): void
    {
        foreach ($message->photoIds as $photoId) {
            $photo = $this->photos->find($photoId);
            foreach (null === $photo ? [] : [$photo->largeId(), $photo->thumbId()] as $fileId) {
                $path = $this->eventPhotoStorage->path($fileId);
                $avif = null === $path ? null : $this->images->avif($path);
                if (null !== $avif) {
                    $this->eventPhotoVariants->storeAvif($fileId, $avif);
                }
            }
        }
    }
}
