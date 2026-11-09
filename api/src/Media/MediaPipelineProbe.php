<?php

declare(strict_types=1);

namespace App\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Essai de bout en bout de la galerie vidéo, dans l'image de production (CI, ou sur le serveur) : fabrique
 * sur place une courte vidéo par format de téléphone, la convertit comme le worker, et vérifie qu'une vidéo
 * réclamant un décodeur hors de la liste (FfmpegCommands::DECODERS) est refusée.
 */
final readonly class MediaPipelineProbe
{
    private const SAMPLE_TIMEOUT = 120;

    public function __construct(
        private FfmpegCommands $commands,
        private VideoTranscoder $transcoder,
        #[Autowire('%kernel.project_dir%/var/storage/video-work')]
        private string $workDirectory,
    ) {
    }

    /**
     * @return list<string> une ligne par essai
     *
     * @throws MediaCheckFailed un format de téléphone refusé, ou le décodeur hors liste accepté
     */
    public function check(): array
    {
        $available = $this->encoders();
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->workDirectory);
        $lines = [];

        foreach (self::samples() as $index => $sample) {
            $video = $sample->videoEncoderAmong($available);
            if (null === $video || !$sample->hasAudioEncoderAmong($available)) {
                if (!$sample->accepted) {
                    throw new MediaCheckFailed($sample->label.' : impossible à fabriquer, le refus n\'est pas vérifié');
                }
                $lines[] = '– '.$sample->label.' : encodeur absent de ce ffmpeg, essai sauté';
                continue;
            }

            $path = $this->workDirectory.'/media-check-'.$index.'.'.$sample->extension;
            try {
                $this->run($this->commands->sample($path, $video, $sample->audioEncoder));
                $lines[] = $this->convert(new VideoSource($path, $sample->container), $sample, 0 === $index);
            } finally {
                $filesystem->remove($path);
            }
        }

        return $lines;
    }

    /** Conversion comme dans le worker ; la première vidéo passe aussi par la version AV1 */
    private function convert(VideoSource $source, MediaSample $sample, bool $withAv1): string
    {
        $filesystem = new Filesystem();
        try {
            $result = $this->transcoder->transcode($source);
        } catch (VideoRejected $e) {
            if ($sample->accepted) {
                throw new MediaCheckFailed($sample->label.' : refusée ('.$e->failure->value.')', 0, $e);
            }

            return '✓ '.$sample->label.' : refusée';
        }
        $filesystem->remove([$result->videoPath, $result->posterPath]);
        if (!$sample->accepted) {
            throw new MediaCheckFailed($sample->label.' : convertie alors que son décodeur n\'est pas autorisé');
        }
        $line = \sprintf('✓ %s : convertie en %d × %d', $sample->label, $result->probe->width, $result->probe->height);

        if ($withAv1) {
            try {
                $av1 = $this->transcoder->compressAv1($source);
            } catch (VideoRejected $e) {
                throw new MediaCheckFailed($sample->label.' : version AV1 impossible', 0, $e);
            }
            $line .= null === $av1 ? ', sans AV1 (encodeur absent)' : ', et en AV1';
            if (null !== $av1) {
                $filesystem->remove($av1);
            }
        }

        return $line;
    }

    /** @return list<MediaSample> */
    private static function samples(): array
    {
        return [
            new MediaSample(label: 'H.264 et AAC en MP4 (Android, WhatsApp)', container: VideoContainer::IsoMedia, extension: 'mp4',
                videoEncoders: [['libx264', '-preset', 'veryfast']], audioEncoder: ['aac'], accepted: true),
            new MediaSample(label: 'HEVC et AAC en MOV (iPhone)', container: VideoContainer::IsoMedia, extension: 'mov',
                videoEncoders: [['libx265', '-preset', 'ultrafast', '-tag:v', 'hvc1']], audioEncoder: ['aac'], accepted: true),
            new MediaSample(label: 'VP9 et Opus en WebM', container: VideoContainer::Matroska, extension: 'webm',
                videoEncoders: [['libvpx-vp9', '-deadline', 'realtime', '-cpu-used', '8', '-b:v', '0', '-crf', '45']], audioEncoder: ['libopus'], accepted: true),
            new MediaSample(label: 'AV1 et Opus en WebM', container: VideoContainer::Matroska, extension: 'webm',
                videoEncoders: [['libsvtav1', '-preset', '12'], ['libaom-av1', '-cpu-used', '8', '-row-mt', '1']], audioEncoder: ['libopus'], accepted: true),
            // Décodeur ancien (MPEG-4 partie 2), hors de la liste : la vidéo doit être refusée
            new MediaSample(label: 'MPEG-4 partie 2 en MP4 (décodeur hors liste)', container: VideoContainer::IsoMedia, extension: 'mp4',
                videoEncoders: [['mpeg4']], audioEncoder: ['aac'], accepted: false),
        ];
    }

    /** @return list<string> */
    private function encoders(): array
    {
        $process = new Process($this->commands->encoders());
        $process->setTimeout(self::SAMPLE_TIMEOUT);
        $process->mustRun();
        preg_match_all('/^\s*[VAS][A-Z.]{5}\s+(\S+)/m', $process->getOutput(), $matches);

        return $matches[1];
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(self::SAMPLE_TIMEOUT);
        $process->mustRun();
    }
}
