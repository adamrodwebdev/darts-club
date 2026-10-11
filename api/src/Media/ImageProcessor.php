<?php

namespace App\Media;

/**
 * Décode une image envoyée et la ré-encode en WebP. Le ré-encodage supprime
 * les métadonnées (EXIF, GPS) et neutralise tout contenu caché du fichier.
 */
final class ImageProcessor
{
    public const AVATAR_SIZE = 256;
    public const PHOTO_MAX_SIDE = 1600;
    public const THUMB_MAX_SIDE = 480;
    /** Affiche sur les cartes du calendrier : nette sur écran haute densité */
    public const POSTER_CARD_MAX_SIDE = 900;
    /** Garde-fou contre les « bombes de décompression » */
    private const MAX_PIXELS = 40_000_000;
    private const ALLOWED_TYPES = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP];

    /** Avatar : recadrage carré centré. */
    public function avatar(string $sourcePath): string
    {
        $source = $this->decode($sourcePath);
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $avatar = $this->canvas(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagecopyresampled($avatar, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::AVATAR_SIZE, self::AVATAR_SIZE, $side, $side);

        return $this->encode($avatar);
    }

    /**
     * Photo de galerie : grande version et vignette, proportions conservées.
     *
     * @return array{large: string, thumb: string, width: int, height: int}
     */
    public function photo(string $sourcePath): array
    {
        $source = $this->decode($sourcePath);
        $large = $this->fit($source, self::PHOTO_MAX_SIDE);

        return [
            'large' => $this->encode($large),
            'thumb' => $this->encode($this->fit($source, self::THUMB_MAX_SIDE)),
            'width' => imagesx($large),
            'height' => imagesy($large),
        ];
    }

    /**
     * Affiche d'événement : grande version et version carte, proportions conservées.
     *
     * @return array{large: string, card: string, width: int, height: int}
     */
    public function poster(string $sourcePath): array
    {
        $source = $this->decode($sourcePath);
        $large = $this->fit($source, self::PHOTO_MAX_SIDE);

        return [
            'large' => $this->encode($large),
            'card' => $this->encode($this->fit($source, self::POSTER_CARD_MAX_SIDE)),
            'width' => imagesx($large),
            'height' => imagesy($large),
        ];
    }

    private function decode(string $path): \GdImage
    {
        $info = @getimagesize($path);
        if (false === $info || !\in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw new InvalidImageException('Format d\'image non pris en charge (JPEG, PNG ou WebP).');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new InvalidImageException('Dimensions d\'image non prises en charge.');
        }

        $image = match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };
        if (false === $image) {
            throw new InvalidImageException('Image illisible.');
        }

        // Respecte l'orientation des photos de smartphone (EXIF supprimé ensuite)
        if (\IMAGETYPE_JPEG === $info[2] && \function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = \is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;
            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
            $image = false !== $rotated ? $rotated : $image;
        }

        return $image;
    }

    private function fit(\GdImage $source, int $maxSide): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxSide / max($width, $height));
        $w = max(1, (int) round($width * $ratio));
        $h = max(1, (int) round($height * $ratio));

        $target = $this->canvas($w, $h);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        return $target;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function canvas(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, 82);
        $binary = (string) ob_get_clean();

        if ('' === $binary) {
            throw new InvalidImageException('Conversion de l\'image impossible.');
        }

        return $binary;
    }
}
