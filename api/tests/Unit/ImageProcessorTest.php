<?php

namespace App\Tests\Unit;

use App\Media\ImageProcessor;
use App\Media\InvalidImageException;
use PHPUnit\Framework\TestCase;

final class ImageProcessorTest extends TestCase
{
    public function testAnImageOverTwentyFiveMegapixelsIsRefusedBeforeDecoding(): void
    {
        // En-tête PNG seul, 6 000 × 5 000 annoncés : refusé sur ses dimensions, sans jamais être décodé
        $path = (string) tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 6000, 5000)."\x08\x02\x00\x00\x00".pack('N', 0));

        try {
            (new ImageProcessor())->photo($path);
            self::fail('Image de 30 mégapixels acceptée');
        } catch (InvalidImageException $e) {
            self::assertSame('Image trop grande (25 mégapixels maximum).', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function testALargePhotoIsReducedToItsLargestVersion(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'jpg');
        imagejpeg(imagecreatetruecolor(1700, 850), $path);

        try {
            $photo = (new ImageProcessor())->photo($path);
        } finally {
            unlink($path);
        }

        self::assertSame([ImageProcessor::PHOTO_MAX_SIDE, 800], [$photo['width'], $photo['height']]);
    }

    public function testAPhotoGetsALighterAvifVersion(): void
    {
        if (!\function_exists('imageavif')) {
            self::markTestSkipped('GD sans AVIF : l\'image de production le vérifie à sa construction.');
        }

        // Image de type photo (dégradés et grain, tirage fixe), réencodée en WebP comme à l'envoi
        mt_srand(71);
        $image = imagecreatetruecolor(1200, 800);
        for ($y = 0; $y < 800; $y += 2) {
            for ($x = 0; $x < 1200; $x += 2) {
                $red = max(0, min(255, intdiv($x * 255, 1200) + mt_rand(0, 40)));
                $green = max(0, min(255, intdiv($y * 255, 800) + mt_rand(0, 40)));
                $color = (int) imagecolorallocate($image, $red, $green, max(0, ($x + $y) % 256));
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $color);
            }
        }
        $jpeg = (string) tempnam(sys_get_temp_dir(), 'jpg');
        $webp = (string) tempnam(sys_get_temp_dir(), 'webp');
        $flat = (string) tempnam(sys_get_temp_dir(), 'webp');
        imagejpeg($image, $jpeg, 92);
        $processor = new ImageProcessor();
        file_put_contents($webp, $processor->photo($jpeg)['large']);
        file_put_contents($flat, $processor->photo(__DIR__.'/../Fixtures/avatar.jpg')['large']);

        try {
            $avif = $processor->avif($webp);
            $webpSize = filesize($webp);
            $flatAvif = $processor->avif($flat);
        } finally {
            unlink($jpeg);
            unlink($webp);
            unlink($flat);
        }

        self::assertNotNull($avif);
        self::assertStringContainsString('ftypavif', substr($avif, 0, 32));
        self::assertLessThan($webpSize, \strlen($avif), 'Gardée seulement plus légère');
        // Image unie de quelques centaines d'octets : l'AVIF n'y gagne rien, le WebP reste seul
        self::assertNull($flatAvif);
    }
}
