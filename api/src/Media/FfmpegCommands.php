<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Lignes de commande ffprobe et ffmpeg, sans shell (liste d'arguments). Chaque appel impose le
 * démultiplexeur, n'autorise que les fichiers locaux et une courte liste de décodeurs : une vidéo
 * piégée ne peut ni ouvrir une liste de lecture, ni joindre le réseau, ni réveiller un décodeur rare.
 * Les conversions tournent en priorité basse, pour ne pas ralentir le site.
 */
final readonly class FfmpegCommands
{
    /**
     * Décodeurs autorisés : ceux des téléphones et des appareils photo (vidéo H.264, HEVC, VP8, VP9, AV1 ;
     * son AAC, Opus, Vorbis, MP3, AC-3, FLAC, ALAC, AMR, PCM). ffmpeg en compte des centaines et la plupart
     * de ses failles se trouvent dans les décodeurs rares, qu'un fichier MP4 ou MKV peut réclamer : une
     * vidéo qui en demande un autre est refusée (illisible).
     */
    public const DECODERS = 'h264,hevc,vp8,vp9,av1,libdav1d,aac,aac_fixed,opus,vorbis,mp3,mp3float,ac3,ac3_fixed,eac3,'
        .'flac,alac,amrnb,amrwb,pcm_s16le,pcm_s16be,pcm_s24le,pcm_s24be,pcm_s32le,pcm_f32le,pcm_u8,pcm_mulaw,pcm_alaw';

    /** Petit côté ramené à 720 px au plus (portrait compris), dimensions paires pour le H.264 et l'AV1 */
    private const SCALE = "scale=w='if(gte(iw,ih),-2,trunc(min(720,iw)/2)*2)':h='if(gte(iw,ih),trunc(min(720,ih)/2)*2,-2)'";

    public function __construct(
        private string $ffmpeg = 'ffmpeg',
        private string $ffprobe = 'ffprobe',
    ) {
    }

    /** @return list<string> */
    public function probe(VideoSource $source): array
    {
        return [
            $this->ffprobe, '-v', 'error', '-protocol_whitelist', 'file', '-codec_whitelist', self::DECODERS,
            '-f', $source->container->value, '-i', $source->path,
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:stream_side_data=rotation:stream_tags=rotate:format=duration',
            '-of', 'json',
        ];
    }

    /**
     * Mesures d'un MP4 que la conversion vient de produire.
     *
     * @return list<string>
     */
    public function probeOutput(string $path): array
    {
        return $this->probe(new VideoSource($path, VideoContainer::IsoMedia));
    }

    /**
     * MP4 H.264 « High » niveau 3.1 et AAC : lisible par tous les navigateurs et téléphones.
     *
     * @return list<string>
     */
    public function h264(VideoSource $source, string $target): array
    {
        return [
            ...$this->input($source, 10),
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '27', '-maxrate', '2500k', '-bufsize', '5000k',
            '-profile:v', 'high', '-level:v', '3.1', '-pix_fmt', 'yuv420p', '-threads', '2',
            '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
            '-movflags', '+faststart', $target,
        ];
    }

    /**
     * MP4 AV1 : même image pour environ deux fois moins d'octets. SVT-AV1 de préférence (rapide),
     * libaom sinon.
     *
     * @return list<string>
     */
    public function av1(VideoSource $source, string $target, string $encoder): array
    {
        $codec = 'libsvtav1' === $encoder
            ? ['-c:v', 'libsvtav1', '-preset', '8', '-crf', '38', '-g', '240']
            : ['-c:v', 'libaom-av1', '-cpu-used', '8', '-row-mt', '1', '-crf', '38', '-b:v', '0', '-g', '240'];

        return [
            ...$this->input($source, 15),
            ...$codec,
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
            '-movflags', '+faststart', $target,
        ];
    }

    /**
     * Image d'aperçu en WebP, tirée du MP4 produit (déjà réduit, sans métadonnées).
     *
     * @return list<string>
     */
    public function poster(string $video, string $target, float $atSeconds): array
    {
        return [
            'nice', '-n', '10', $this->ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file', '-codec_whitelist', self::DECODERS, '-f', VideoContainer::IsoMedia->value,
            '-ss', \sprintf('%.2F', $atSeconds), '-i', $video,
            '-frames:v', '1', '-c:v', 'libwebp', '-quality', '75', $target,
        ];
    }

    /** @return list<string> */
    public function encoders(): array
    {
        return [$this->ffmpeg, '-hide_banner', '-encoders'];
    }

    /**
     * Vidéo d'essai de deux secondes (mire et son) fabriquée sur place, pour app:media:check.
     *
     * @param list<string> $video encodeur vidéo et ses réglages
     * @param list<string> $audio encodeur son et ses réglages
     *
     * @return list<string>
     */
    public function sample(string $target, array $video, array $audio): array
    {
        return [
            $this->ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', 'testsrc2=size=640x360:rate=30:duration=2',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
            '-c:v', ...$video, '-pix_fmt', 'yuv420p', '-c:a', ...$audio, '-shortest', $target,
        ];
    }

    /**
     * Entrée commune : démultiplexeur imposé, fichiers locaux et décodeurs de la liste seulement, une seule
     * piste image et son, ni métadonnées (lieu GPS des téléphones), ni chapitres, ni sous-titres, 61 s au plus.
     *
     * @return list<string>
     */
    private function input(VideoSource $source, int $niceness): array
    {
        return [
            'nice', '-n', (string) $niceness, $this->ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file', '-codec_whitelist', self::DECODERS, '-f', $source->container->value, '-i', $source->path,
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1', '-sn', '-dn',
            '-vf', self::SCALE, '-fpsmax', '30', '-t', '61',
        ];
    }
}
