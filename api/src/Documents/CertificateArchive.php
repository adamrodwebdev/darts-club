<?php

namespace App\Documents;

use App\Media\FileStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Attestations d'assurance : PDF seulement (reconnu à sa signature), rangés hors de la racine web. */
final class CertificateArchive
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        #[Autowire(service: 'app.storage.documents')]
        private readonly FileStorage $storage,
    ) {
    }

    /** @return string identifiant du fichier */
    public function store(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DocumentException('« %file% » : fichier invalide ou trop lourd (10 Mo maximum).', null, ['%file%' => mb_substr($file->getClientOriginalName(), 0, 60)]);
        }
        $path = $file->getPathname();
        if ('%PDF-' !== file_get_contents($path, false, null, 0, 5)) {
            throw new DocumentException('L’attestation doit être un PDF.');
        }

        return $this->storage->store((string) file_get_contents($path), 'pdf');
    }

    public function path(string $fileId): ?string
    {
        return $this->storage->path($fileId);
    }

    public function delete(string $fileId): void
    {
        $this->storage->delete($fileId);
    }
}
