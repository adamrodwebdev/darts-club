<?php

namespace App\Media;

use App\Enum\VideoFailure;
use App\Security\TokenGenerator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Conversion par ffmpeg, dans le worker : jamais pendant une requête du site. Mesure avant de
 * décoder (dimensions, durée), puis produit des fichiers neufs aux noms aléatoires : l'original
 * envoyé n'est jamais servi. Déclaré dans config/services.yaml avec son dossier de travail.
 */
#[Exclude]
final class FfmpegVideoTranscoder implements VideoTranscoder
{
    /** Une minute de vidéo : quelques minutes de calcul au plus, même en AV1 */
    private const CONVERSION_TIMEOUT = 900;

    private const PROBE_TIMEOUT = 60;

    private readonly Filesystem $filesystem;

    /** Encodeur AV1 disponible, relevé une fois : 'libsvtav1', 'libaom-av1' ou '' */
    private ?string $av1Encoder = null;

    public function __construct(
        private readonly FfmpegCommands $commands,
        private readonly string $workDirectory,
        private readonly TokenGenerator $tokens,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function transcode(VideoSource $source): TranscodedVideo
    {
        $measured = $this->probe($this->commands->probe($source));
        if (max($measured->width, $measured->height) > self::MAX_SIDE) {
            throw new VideoRejected(VideoFailure::Oversized);
        }
        if ($measured->durationMs > self::MAX_DURATION_MS) {
            throw new VideoRejected(VideoFailure::TooLong);
        }

        $video = $this->temporaryFile('mp4');
        $poster = $this->temporaryFile('webp');
        try {
            $this->run($this->commands->h264($source, $video));
            $result = $this->probe($this->commands->probeOutput($video));
            $this->run($this->commands->poster($video, $poster, min(1.0, $result->durationMs / 2000)));
        } catch (\Throwable $e) {
            $this->filesystem->remove([$video, $poster]);
            throw $e instanceof VideoRejected ? $e : new VideoRejected(VideoFailure::Unreadable, $e);
        }

        return new TranscodedVideo($video, $poster, $result);
    }

    public function compressAv1(VideoSource $source): ?string
    {
        $encoder = $this->av1Encoder();
        if ('' === $encoder) {
            return null;
        }

        $target = $this->temporaryFile('mp4');
        try {
            $this->run($this->commands->av1($source, $target, $encoder));
        } catch (\Throwable $e) {
            $this->filesystem->remove($target);
            throw new VideoRejected(VideoFailure::Unreadable, $e);
        }

        return $target;
    }

    private function av1Encoder(): string
    {
        if (null === $this->av1Encoder) {
            $process = new Process($this->commands->encoders());
            $process->setTimeout(self::PROBE_TIMEOUT);
            $process->run();
            $list = $process->isSuccessful() ? $process->getOutput() : '';
            $this->av1Encoder = match (true) {
                1 === preg_match('/\slibsvtav1\s/', $list) => 'libsvtav1',
                1 === preg_match('/\slibaom-av1\s/', $list) => 'libaom-av1',
                default => '',
            };
        }

        return $this->av1Encoder;
    }

    /** @param list<string> $command */
    private function probe(array $command): VideoProbe
    {
        $process = new Process($command);
        $process->setTimeout(self::PROBE_TIMEOUT);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new VideoRejected(VideoFailure::Unreadable);
        }

        return VideoProbe::fromJson($process->getOutput());
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(self::CONVERSION_TIMEOUT);
        $process->mustRun();
    }

    private function temporaryFile(string $extension): string
    {
        $this->filesystem->mkdir($this->workDirectory);

        return $this->workDirectory.'/'.$this->tokens->hex(16).'.'.$extension;
    }
}
