<?php

namespace App\Dto;

use App\Gallery\UploadDeclaration;
use Symfony\Component\Validator\Constraints as Assert;

/** Annonce d'un envoi de vidéo : nom, taille et durée lues sur l'appareil, type du fichier. */
final class VideoUploadInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $fileName = '',
        #[Assert\Positive]
        public readonly int $size = 0,
        /** 0 : durée inconnue de l'appareil, mesurée par le serveur */
        #[Assert\PositiveOrZero]
        public readonly int $durationMs = 0,
        #[Assert\Choice(choices: UploadDeclaration::MEDIA_TYPES, message: 'Format non pris en charge : envoie une vidéo MP4, MOV ou WebM.')]
        public readonly string $mediaType = '',
    ) {
    }

    public function toDeclaration(): UploadDeclaration
    {
        return new UploadDeclaration($this->fileName, $this->size, $this->durationMs);
    }
}
