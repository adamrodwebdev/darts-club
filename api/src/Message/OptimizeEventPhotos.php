<?php

namespace App\Message;

/** Photos de galerie déjà en ligne (WebP) : le worker en ajoute les versions AVIF, plus légères. */
final readonly class OptimizeEventPhotos
{
    /** @param list<string> $photoIds */
    public function __construct(
        public array $photoIds,
    ) {
    }
}
