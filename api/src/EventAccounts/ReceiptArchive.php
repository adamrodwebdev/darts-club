<?php

namespace App\EventAccounts;

use App\Media\FileStorage;
use App\Media\ImageProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Fichiers des justificatifs : une photo est réencodée en WebP (métadonnées et position GPS
 * supprimées), un PDF est gardé tel quel (reconnu à sa signature, jamais à son nom ou à son type
 * annoncé). Rangés hors de la racine web.
 */
final class ReceiptArchive
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly ImageProcessor $images,
        #[Autowire(service: 'app.storage.receipts')]
        private readonly FileStorage $storage,
    ) {
    }

    public function store(UploadedFile $file): StoredReceipt
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new AccountsException('« %file% » : fichier invalide ou trop lourd (10 Mo maximum).', null, ['%file%' => mb_substr($file->getClientOriginalName(), 0, 60)]);
        }

        $path = $file->getPathname();
        if (self::isPdf($path)) {
            return new StoredReceipt($this->storage->store((string) file_get_contents($path), 'pdf'), StoredReceipt::PDF);
        }
        if (false === @getimagesize($path)) {
            throw new AccountsException('Justificatif : une photo (JPEG, PNG ou WebP) ou un PDF.');
        }

        return new StoredReceipt($this->storage->store($this->images->receipt($path), 'webp'), StoredReceipt::IMAGE);
    }

    public function path(StoredReceipt $file): ?string
    {
        return $this->storage->path($file->fileId);
    }

    public function delete(StoredReceipt $file): void
    {
        $this->storage->delete($file->fileId);
    }

    private static function isPdf(string $path): bool
    {
        return '%PDF-' === file_get_contents($path, false, null, 0, 5);
    }
}
