<?php

namespace App\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Image reçue d'un formulaire : fichier arrivé entier et d'au plus 10 Mo, sinon refusée en la nommant. */
final readonly class ImageUpload
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    private function __construct(public string $path)
    {
    }

    public static function from(UploadedFile $file): self
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidImageException('« %file% » : fichier invalide ou trop lourd (10 Mo maximum).', null, ['%file%' => mb_substr($file->getClientOriginalName(), 0, 60)]);
        }

        return new self($file->getPathname());
    }
}
