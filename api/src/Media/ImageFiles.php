<?php

namespace App\Media;

/** Une image servie aux membres : WebP pour tous, AVIF quand elle existe, étiquette de cache. */
final readonly class ImageFiles
{
    public function __construct(
        public string $webpPath,
        public ?string $avifPath,
        public string $etag,
    ) {
    }
}
