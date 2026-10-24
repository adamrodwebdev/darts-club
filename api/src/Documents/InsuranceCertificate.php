<?php

namespace App\Documents;

/** Attestation d'assurance responsabilité civile délivrée par l'assureur (PDF rangé hors de la racine web). */
final readonly class InsuranceCertificate
{
    public function __construct(
        public string $fileId,
        public \DateTimeImmutable $validUntil,
    ) {
    }

    public function isValidOn(\DateTimeImmutable $day): bool
    {
        return $this->validUntil >= $day->setTime(0, 0);
    }
}
