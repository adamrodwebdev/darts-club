<?php

declare(strict_types=1);

namespace App\Media;

/** Résultat d'une conversion : fichiers temporaires (MP4, aperçu WebP) à ranger, mesures du MP4 produit. */
final readonly class TranscodedVideo
{
    public function __construct(
        public string $videoPath,
        public string $posterPath,
        public VideoProbe $probe,
    ) {
    }
}
