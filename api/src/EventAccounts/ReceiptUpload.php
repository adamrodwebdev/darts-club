<?php

namespace App\EventAccounts;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Justificatif reçu d'un formulaire : le fichier envoyé et ce qu'en dit le bureau. */
final readonly class ReceiptUpload
{
    public function __construct(
        public UploadedFile $file,
        public ReceiptDetails $details,
    ) {
    }
}
