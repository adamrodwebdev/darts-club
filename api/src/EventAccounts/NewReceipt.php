<?php

namespace App\EventAccounts;

/** Justificatif à ranger dans un bilan : ce qu'en dit le bureau et son fichier déjà stocké. */
final readonly class NewReceipt
{
    public function __construct(
        public ReceiptDetails $details,
        public StoredReceipt $file,
    ) {
    }
}
