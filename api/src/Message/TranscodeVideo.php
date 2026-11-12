<?php

declare(strict_types=1);

namespace App\Message;

/** Envoi complet : le worker convertit la vidéo en MP4 H.264 et tire son image d'aperçu. */
final readonly class TranscodeVideo
{
    public function __construct(
        public string $videoId,
    ) {
    }
}
