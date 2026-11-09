<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Ce que l'appareil annonce avant d'envoyer une vidéo : nom, taille, durée lue sur l'appareil
 * (0 quand le navigateur ne sait pas la lire, une vidéo HEVC sous Firefox par exemple). La durée
 * est de toute façon mesurée par le serveur à la conversion.
 */
final readonly class UploadDeclaration
{
    public const MAX_BYTES = 500 * 1024 * 1024;

    public const MAX_DURATION_MS = 60_000;

    /** Types envoyés par les téléphones et les ordinateurs (MP4, MOV de l'iPhone, WebM, 3GP) */
    public const MEDIA_TYPES = ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp', 'video/x-m4v'];

    public string $fileName;

    public function __construct(
        string $fileName,
        public int $size,
        public int $durationMs,
    ) {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $fileName) ?? '');
        $this->fileName = '' === $name ? 'video' : mb_substr($name, 0, 120);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new GalleryException('Une vidéo pèse %max% Mo au plus.', null, ['%max%' => intdiv(self::MAX_BYTES, 1024 * 1024)]);
        }
        if ($durationMs < 0 || $durationMs > self::MAX_DURATION_MS) {
            throw new GalleryException('Une vidéo dure %max% secondes au plus.', null, ['%max%' => intdiv(self::MAX_DURATION_MS, 1000)]);
        }
    }
}
