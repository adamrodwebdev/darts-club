<?php

namespace App\ClubEvent;

/**
 * Affiche d'un événement : grande version (page de l'événement) et version
 * carte (calendrier), en WebP. Les identifiants de fichiers figurent dans les
 * URL : une nouvelle affiche change d'URL, le cache navigateur reste sûr.
 */
final readonly class Poster
{
    public function __construct(
        public string $largeId,
        public string $cardId,
        public int $width,
        public int $height,
    ) {
    }

    public function has(string $fileId): bool
    {
        return $fileId === $this->largeId || $fileId === $this->cardId;
    }

    /** @return array{largeUrl: string, cardUrl: string, width: int, height: int} */
    public function toArray(string $eventId): array
    {
        return $this->at('/api/events/'.$eventId.'/poster/');
    }

    /** @return array{largeUrl: string, cardUrl: string, width: int, height: int} */
    public function toPublicArray(string $slug): array
    {
        return $this->at('/api/public/events/'.$slug.'/poster/');
    }

    /** @return array{largeUrl: string, cardUrl: string, width: int, height: int} */
    private function at(string $base): array
    {
        return [
            'largeUrl' => $base.$this->largeId,
            'cardUrl' => $base.$this->cardId,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
