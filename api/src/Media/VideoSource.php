<?php

namespace App\Media;

/** Fichier envoyé, avec le conteneur reconnu à sa signature. */
final readonly class VideoSource
{
    public function __construct(
        public string $path,
        public VideoContainer $container,
    ) {
    }
}
