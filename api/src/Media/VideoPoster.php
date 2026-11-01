<?php

namespace App\Media;

/** Image d'aperçu d'une vidéo : WebP pour tous, AVIF plus légère pour les navigateurs qui la lisent. */
final readonly class VideoPoster
{
    public function __construct(
        public string $webpId,
        public ?string $avifId,
    ) {
    }
}
