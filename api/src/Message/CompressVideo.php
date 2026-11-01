<?php

namespace App\Message;

/** Vidéo déjà lisible : le worker en ajoute une version AV1 plus légère, puis efface l'original. */
final readonly class CompressVideo
{
    public function __construct(
        public string $videoId,
    ) {
    }
}
