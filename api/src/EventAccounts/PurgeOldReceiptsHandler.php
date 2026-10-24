<?php

namespace App\EventAccounts;

use App\Entity\Receipt;
use App\Repository\ReceiptRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Efface les justificatifs (fichiers et lignes) des soirées terminées depuis plus de 6 ans. */
#[AsMessageHandler]
final class PurgeOldReceiptsHandler
{
    /** Durée de conservation des pièces justificatives (livre des procédures fiscales, art. L102 B) */
    public const RETENTION = 'P6Y';

    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly ReceiptArchive $archive,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgeOldReceipts $message): void
    {
        $expired = $this->receipts->forEventsEndedBefore($this->clock->now()->sub(new \DateInterval(self::RETENTION)));
        $files = array_map(static fn (Receipt $receipt) => $receipt->file(), $expired);

        $this->receipts->deleteAll(...$expired);
        foreach ($files as $file) {
            $this->archive->delete($file);
        }
    }
}
