<?php

namespace App\ClubEvent;

/** Photo de galerie enregistrée : ses deux fichiers (grand format, vignette) et ses dimensions. */
final readonly class StoredPhoto
{
    public function __construct(
        public string $largeId,
        public string $thumbId,
        public int $width,
        public int $height,
    ) {
    }
}
