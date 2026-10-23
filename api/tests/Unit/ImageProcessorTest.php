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
        imagejpeg(imagecreatetruecolor(2000, 1500), $path);

        try {
            $photo = (new ImageProcessor())->photo($path);
        } finally {
            unlink($path);
        }

        self::assertSame([ImageProcessor::PHOTO_MAX_SIDE, 1200], [$photo['width'], $photo['height']]);
    }
}
