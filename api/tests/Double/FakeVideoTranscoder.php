<?php

namespace App\Tests\Double;

use App\Enum\VideoFailure;
use App\Media\TranscodedVideo;
use App\Media\VideoProbe;
use App\Media\VideoRejected;
use App\Media\VideoSource;
use App\Media\VideoTranscoder;

/**
 * Conversion simulée (pas de ffmpeg dans la CI). La « vidéo » de test porte une vraie signature
 * MP4 et annonce sa durée réelle en clair (« DUREE=65000 ») : mêmes refus que ffmpeg.
 */
final class FakeVideoTranscoder implements VideoTranscoder
{
    public function transcode(VideoSource $source): TranscodedVideo
    {
        $content = (string) file_get_contents($source->path);
        $duration = 1 === preg_match('/DUREE=(\d+)/', $content, $match) ? (int) $match[1] : 5000;
        if ($duration > self::MAX_DURATION_MS) {
            throw new VideoRejected(VideoFailure::TooLong);
        }

        $video = $this->file('h264', str_repeat('h', 4000));
        $poster = $this->file('poster', '');
        $image = imagecreatetruecolor(16, 9);
        imagewebp($image, $poster);

        return new TranscodedVideo($video, $poster, new VideoProbe(1280, 720, $duration));
    }

    public function compressAv1(VideoSource $source): string
    {
        return $this->file('av1', str_repeat('a', 1500));
    }

    private function file(string $prefix, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if (false === $path) {
            throw new \RuntimeException('Fichier temporaire impossible.');
        }
        file_put_contents($path, $content);

        return $path;
    }
}
