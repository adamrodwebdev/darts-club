<?php

namespace App\Media;

/**
 * Vidéo convertie et rangée : MP4 H.264 lisible partout, image d'aperçu (WebP, et AVIF plus légère
 * quand le serveur sait l'encoder), mesures relevées sur le résultat.
 */
final readonly class StoredVideo
{
    public function __construct(
        public string $fileId,
        public int $fileSize,
        public VideoPoster $poster,
        public VideoProbe $probe,
    ) {
    }
}
