<?php

namespace App\Documents;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Remplace l'attestation d'assurance de la fiche : le nouveau fichier entre, l'ancien est effacé. */
final class InsuranceDesk
{
    public function __construct(
        private readonly AssociationProfiles $profiles,
        private readonly CertificateArchive $archive,
    ) {
    }

    public function replace(User $actor, UploadedFile $file, \DateTimeImmutable $validUntil): void
    {
        $previous = $this->profiles->current()->certificate();
        $fileId = $this->archive->store($file);
        $this->profiles->replaceCertificate($actor, new InsuranceCertificate($fileId, $validUntil));
        if (null !== $previous) {
            $this->archive->delete($previous->fileId);
        }
    }
}
