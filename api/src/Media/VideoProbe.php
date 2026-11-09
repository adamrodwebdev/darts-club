<?php

declare(strict_types=1);

namespace App\Media;

use App\Enum\VideoFailure;

/** Mesures d'une vidéo relevées par ffprobe : durée et dimensions affichées (rotation comprise). */
final readonly class VideoProbe
{
    public function __construct(
        public int $width,
        public int $height,
        public int $durationMs,
    ) {
    }

    /**
     * Lit la sortie JSON de `ffprobe -show_entries stream=width,height:stream_side_data=rotation:stream_tags=rotate:format=duration`.
     * Une vidéo tournée d'un quart de tour (portrait filmé au téléphone) échange largeur et hauteur.
     *
     * @throws VideoRejected sans flux vidéo ou sans durée
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        $stream = \is_array($data) && \is_array($data['streams'] ?? null) ? ($data['streams'][0] ?? null) : null;
        $duration = \is_array($data) && \is_array($data['format'] ?? null) ? ($data['format']['duration'] ?? null) : null;
        if (!\is_array($stream) || !is_numeric($stream['width'] ?? null) || !is_numeric($stream['height'] ?? null) || !is_numeric($duration)) {
            throw new VideoRejected(VideoFailure::Unreadable);
        }

        $width = (int) $stream['width'];
        $height = (int) $stream['height'];
        if ($width < 1 || $height < 1) {
            throw new VideoRejected(VideoFailure::Unreadable);
        }

        $quarterTurn = 90 === abs(self::rotation($stream)) % 180;

        return new self(
            width: $quarterTurn ? $height : $width,
            height: $quarterTurn ? $width : $height,
            durationMs: (int) round((float) $duration * 1000),
        );
    }

    /** @param array<mixed> $stream */
    private static function rotation(array $stream): int
    {
        foreach (\is_array($stream['side_data_list'] ?? null) ? $stream['side_data_list'] : [] as $side) {
            if (\is_array($side) && is_numeric($side['rotation'] ?? null)) {
                return (int) $side['rotation'];
            }
        }
        $tags = \is_array($stream['tags'] ?? null) ? $stream['tags'] : [];

        return is_numeric($tags['rotate'] ?? null) ? (int) $tags['rotate'] : 0;
    }
}
