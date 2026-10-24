<?php

namespace App\Documents;

use App\Entity\EventAccounts;

/** Faits du moment pour un document : date du jour, bilan de la soirée, buvettes déjà comptées dans l'année. */
final readonly class DocumentFacts
{
    public function __construct(
        public \DateTimeImmutable $today,
        public ?EventAccounts $accounts = null,
        /** Soirées de l'année, avant celle-ci, comptées comme buvette temporaire */
        public int $earlierTemporaryBars = 0,
    ) {
    }
}
