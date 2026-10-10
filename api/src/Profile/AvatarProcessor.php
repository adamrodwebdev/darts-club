<?php

namespace App\Profile;

/**
 * Décode l'image envoyée, la recadre en carré et la ré-encode en WebP.
 * Le ré-encodage supprime les métadonnées (EXIF, GPS) et neutralise
 * tout contenu caché dans le fichier d'origine.
 */
final class AvatarProcessor
{
    public const SIZE = 256;
    /** Garde-fou contre les « bombes de décompression » */
    private const MAX_PIXELS = 25_000_000;
    private const ALLOWED_TYPES = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP];

    public function process(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);
        if (false === $info || !\in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw new InvalidAvatarException('Format d\'image non pris en charge (JPEG, PNG ou WebP).');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new InvalidAvatarException('Dimensions d\'image non prises en charge.');
        }

        $source = match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            \IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            default => @imagecreatefromwebp($sourcePath),
        };
        if (false === $source) {
            throw new InvalidAvatarException('Image illisible.');
        }

        // Recadrage centré (cover)
        $side = min($width, $height);
        $avatar = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($avatar, false);
        imagesavealpha($avatar, true);
        imagecopyresampled(
            $avatar, $source,
            0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side,
        );

        ob_start();
        imagewebp($avatar, null, 82);
        $binary = (string) ob_get_clean();

        if ('' === $binary) {
            throw new InvalidAvatarException('Conversion de l\'image impossible.');
        }

        return $binary;
    }
}
