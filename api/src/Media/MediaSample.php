<?php

declare(strict_types=1);

namespace App\Media;

/** Vidéo d'essai de app:media:check : un format de téléphone, et ce que la galerie doit en faire. */
final readonly class MediaSample
{
    /**
     * @param list<non-empty-list<string>> $videoEncoders encodeurs vidéo et leurs réglages, par préférence (le premier présent sert)
     * @param non-empty-list<string>       $audioEncoder  encodeur son et ses réglages
     */
    public function __construct(
        public string $label,
        public VideoContainer $container,
        public string $extension,
        public array $videoEncoders,
        public array $audioEncoder,
        public bool $accepted,
    ) {
    }

    /**
     * Premier encodeur vidéo disponible dans ce ffmpeg, avec ses réglages ; null si aucun.
     *
     * @param list<string> $available noms des encodeurs de ffmpeg
     *
     * @return non-empty-list<string>|null
     */
    public function videoEncoderAmong(array $available): ?array
    {
        foreach ($this->videoEncoders as $encoder) {
            if (\in_array($encoder[0], $available, true)) {
                return $encoder;
            }
        }

        return null;
    }

    /** @param list<string> $available noms des encodeurs de ffmpeg */
    public function hasAudioEncoderAmong(array $available): bool
    {
        return \in_array($this->audioEncoder[0], $available, true);
    }
}
