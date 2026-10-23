<?php

namespace App\ClubEvent;

use App\Entity\EventPhoto;
use App\Media\ImageProcessor;
use App\Media\ImageStorage;
use App\Media\ImageUpload;

/** Fichiers des photos de galerie : ré-encodées en deux tailles (WebP), enregistrées puis supprimées ensemble. */
final class EventPhotoFiles
{
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly ImageStorage $eventPhotoStorage,
    ) {
    }

    public function store(ImageUpload $upload): StoredPhoto
    {
        $processed = $this->images->photo($upload->path);

        return new StoredPhoto(
            largeId: $this->eventPhotoStorage->store($processed['large']),
            thumbId: $this->eventPhotoStorage->store($processed['thumb']),
            width: $processed['width'],
            height: $processed['height'],
        );
    }

    public function delete(EventPhoto $photo): void
    {
        $this->eventPhotoStorage->delete($photo->largeId());
        $this->eventPhotoStorage->delete($photo->thumbId());
    }

    /** Fichiers enregistrés pour une photo finalement refusée (envoi interrompu) */
    public function discard(StoredPhoto $photo): void
    {
        $this->eventPhotoStorage->delete($photo->largeId);
        $this->eventPhotoStorage->delete($photo->thumbId);
    }
}
