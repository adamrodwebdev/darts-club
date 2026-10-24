<?php

namespace App\EventAccounts;

/** Fichier d'un justificatif rangé hors de la racine web : identifiant opaque et type servi. */
final readonly class StoredReceipt
{
    public const IMAGE = 'image/webp';
    public const PDF = 'application/pdf';

    public function __construct(
        public string $fileId,
        public string $mediaType,
    ) {
    }

    public function isPdf(): bool
    {
        return self::PDF === $this->mediaType;
    }
}
