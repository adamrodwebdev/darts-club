<?php

namespace App\Documents;

use App\Entity\AssociationProfile;
use Symfony\Component\Clock\ClockInterface;

/** Fiche de l'association pour le bureau : ce que reprennent les documents, ce qui manque, l'attestation. */
final class AssociationProfileView
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function of(AssociationProfile $profile, bool $mayEdit): array
    {
        $details = $profile->details();
        $certificate = $profile->certificate();

        return [
            'officialName' => $details->officialName,
            'rnaNumber' => $details->rnaNumber,
            'siret' => $details->siret,
            'seatAddress' => $details->seatAddress,
            'presidentName' => $details->presidentName,
            'secretaryName' => $details->secretaryName,
            'treasurerName' => $details->treasurerName,
            'insurerName' => $details->insurerName,
            'insurancePolicy' => $details->insurancePolicy,
            'missing' => $details->missing(),
            'certificate' => null === $certificate ? null : [
                'validUntil' => $certificate->validUntil->format('Y-m-d'),
                'valid' => $certificate->isValidOn($this->clock->now()),
            ],
            'updatedAt' => $profile->updatedAt()?->format(\DATE_ATOM),
            'updatedBy' => $profile->updatedBy()?->displayName()->value,
            'mayEdit' => $mayEdit,
        ];
    }
}
