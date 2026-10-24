<?php

namespace App\EventAccounts;

use App\Entity\ClubEvent;
use App\Entity\Receipt;
use App\Entity\User;

/** Range ou retire un justificatif : le fichier et sa ligne dans le bilan vont ensemble. */
final class ReceiptDesk
{
    public function __construct(
        private readonly ReceiptArchive $archive,
        private readonly Bookkeeper $bookkeeper,
    ) {
    }

    public function add(User $actor, ClubEvent $event, ReceiptUpload $upload): void
    {
        $file = $this->archive->store($upload->file);
        try {
            $this->bookkeeper->addReceipt($actor, $event, new NewReceipt($upload->details, $file));
        } catch (\Throwable $e) {
            // Bilan clôturé ou enregistrement impossible : aucun fichier orphelin
            $this->archive->delete($file);
            throw $e;
        }
    }

    public function remove(User $actor, Receipt $receipt): void
    {
        $file = $receipt->file();
        $this->bookkeeper->removeReceipt($actor, $receipt);
        $this->archive->delete($file);
    }
}
