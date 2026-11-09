<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\VideoFailure;
use App\Media\FfmpegCommands;
use App\Media\VideoContainer;
use App\Media\VideoProbe;
use App\Media\VideoRejected;
use App\Media\VideoSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Règles de sûreté et de légèreté de la galerie, sans ffmpeg. */
final class GalleryMediaTest extends TestCase
{
    public function testContainersAreRecognisedByTheirSignatureOnly(): void
    {
        self::assertSame(VideoContainer::IsoMedia, VideoContainer::fromSignature("\x00\x00\x00\x18ftypqt  "));
        self::assertSame(VideoContainer::Matroska, VideoContainer::fromSignature("\x1A\x45\xDF\xA3\x9F\x42\x86\x81"));
        // Listes de lecture et scripts : jamais confiés à ffmpeg, quel que soit leur nom
        self::assertNull(VideoContainer::fromSignature("#EXTM3U\n#EXT-X-VERSION:3"));
        self::assertNull(VideoContainer::fromSignature('ffconcat version 1.0'));
        self::assertNull(VideoContainer::fromSignature(''));
    }

    public function testEveryCommandForcesTheDemuxerAndStaysOnLocalFiles(): void
    {
        $commands = new FfmpegCommands('ffmpeg', 'ffprobe');
        $source = new VideoSource('/var/storage/video-uploads/a.part', VideoContainer::IsoMedia);

        $decoding = [
            $commands->probe($source),
            $commands->h264($source, '/tmp/out.mp4'),
            $commands->av1($source, '/tmp/out.mp4', 'libsvtav1'),
            $commands->poster('/tmp/out.mp4', '/tmp/out.webp', 1.0),
        ];
        foreach ($decoding as $command) {
            $input = array_search('-i', $command, true);
            self::assertSame('mov', $command[array_search('-f', $command, true) + 1]);
            self::assertSame('file', $command[array_search('-protocol_whitelist', $command, true) + 1]);
            self::assertSame(FfmpegCommands::DECODERS, $command[array_search('-codec_whitelist', $command, true) + 1]);
            self::assertLessThan($input, array_search('-f', $command, true), 'Démultiplexeur imposé avant l\'entrée');
            self::assertLessThan($input, array_search('-codec_whitelist', $command, true), 'Décodeurs limités avant l\'entrée');
        }
    }

    public function testOnlyTheDecodersOfPhonesAndCamerasAreAllowed(): void
    {
        $decoders = explode(',', FfmpegCommands::DECODERS);

        foreach (['h264', 'hevc', 'vp9', 'av1', 'libdav1d', 'aac', 'opus'] as $phone) {
            self::assertContains($phone, $decoders);
        }
        // Décodeurs rares ou anciens, d'où viennent la plupart des failles de ffmpeg : refusés
        foreach (['mpeg4', 'h263', 'msmpeg4v3', 'cinepak', 'smackvid', 'dxa', 'mov_text', 'ass'] as $rare) {
            self::assertNotContains($rare, $decoders);
        }
    }

    public function testConversionsDropMetadataAndUseTheLightestCodecs(): void
    {
        $commands = new FfmpegCommands();
        $source = new VideoSource('in.webm', VideoContainer::Matroska);
        $h264 = $commands->h264($source, 'out.mp4');
        $av1 = $commands->av1($source, 'out.mp4', 'libaom-av1');

        foreach ([$h264, $av1] as $command) {
            // Lieu GPS des téléphones, chapitres, sous-titres : rien ne passe
            self::assertSame('-1', $command[array_search('-map_metadata', $command, true) + 1]);
            self::assertContains('-sn', $command);
            self::assertContains('+faststart', $command);
            self::assertSame('nice', $command[0], 'Priorité basse : le site reste rapide pendant la conversion');
        }
        self::assertContains('libx264', $h264);
        self::assertContains('libaom-av1', $av1);
        self::assertSame('matroska', $h264[array_search('-f', $h264, true) + 1]);
    }

    public function testTheProbeSwapsSidesForPortraitVideosAndRefusesEmptyOnes(): void
    {
        $portrait = VideoProbe::fromJson('{"streams":[{"width":1920,"height":1080,"side_data_list":[{"rotation":-90}]}],"format":{"duration":"12.480"}}');
        self::assertSame([1080, 1920, 12480], [$portrait->width, $portrait->height, $portrait->durationMs]);

        $tagged = VideoProbe::fromJson('{"streams":[{"width":1280,"height":720,"tags":{"rotate":"90"}}],"format":{"duration":"3"}}');
        self::assertSame([720, 1280], [$tagged->width, $tagged->height]);

        try {
            VideoProbe::fromJson('{"streams":[],"format":{"duration":"3"}}');
            self::fail('Sans flux vidéo');
        } catch (VideoRejected $e) {
            self::assertSame(VideoFailure::Unreadable, $e->failure);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function forgedProbes(): iterable
    {
        yield 'une largeur écrite en texte' => ['{"streams":[{"width":"1920","height":1080}],"format":{"duration":"3"}}'];
        yield 'une hauteur décimale' => ['{"streams":[{"width":1920,"height":1080.5}],"format":{"duration":"3"}}'];
        yield 'une durée en exposant' => ['{"streams":[{"width":1920,"height":1080}],"format":{"duration":"1e3"}}'];
        yield 'une durée qui n\'est pas un nombre' => ['{"streams":[{"width":1920,"height":1080}],"format":{"duration":"NaN"}}'];
        yield 'une durée écrite en nombre' => ['{"streams":[{"width":1920,"height":1080}],"format":{"duration":3}}'];
    }

    #[DataProvider('forgedProbes')]
    public function testTheProbeReadsOnlyTheTypesFfprobeWrites(string $json): void
    {
        $this->expectException(VideoRejected::class);

        VideoProbe::fromJson($json);
    }
}
