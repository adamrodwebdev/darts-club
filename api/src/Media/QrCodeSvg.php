<?php

namespace App\Media;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR code imprimable (SVG) vers une page du site : correction d'erreurs haute, pour que le logo
 * posé au centre de l'affiche ne gêne pas la lecture, et marge blanche autour (zone de silence).
 */
final class QrCodeSvg
{
    private const SIZE = 600;
    private const QUIET_ZONE = 64;

    public function of(string $url): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: self::SIZE,
            margin: self::QUIET_ZONE,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(21, 21, 26),
            backgroundColor: new Color(255, 255, 255),
        ))->build()->getString();
    }
}
