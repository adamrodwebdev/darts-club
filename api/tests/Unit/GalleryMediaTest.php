<?php

namespace App\Tests\Unit;

use App\Enum\VideoFailure;
use App\Media\FfmpegCommands;
use App\Media\VideoContainer;
use App\Media\VideoProbe;
use App\Media\VideoRejected;
use App\Media\VideoSource;
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

        foreach ([$commands->probe($source), $commands->h264($source, '/tmp/out.mp4'), $commands->av1($source, '/tmp/out.mp4', 'libsvtav1')] as $command) {
            self::assertSame('mov', $command[array_search('-f', $command, true) + 1]);
            self::assertSame('file', $command[array_search('-protocol_whitelist', $command, true) + 1]);
            self::assertLessThan(array_search('-i', $command, true), array_search('-f', $command, true), 'Démultiplexeur imposé avant l\'entrée');
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
}
