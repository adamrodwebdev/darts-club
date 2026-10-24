<?php

namespace App\Documents;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\AssociationProfile;
use App\Entity\User;
use App\Repository\AssociationProfileRepository;
use Symfony\Component\Clock\ClockInterface;

/** Tient la fiche de l'association ; chaque modification s'inscrit au journal. */
final class AssociationProfiles
{
    public function __construct(
        private readonly AssociationProfileRepository $profiles,
        private readonly AuditTrail $audit,
        private readonly ClockInterface $clock,
    ) {
    }

    public function current(): AssociationProfile
    {
        return $this->profiles->current();
    }

    public function update(User $actor, AssociationDetails $details): void
    {
        $profile = $this->profiles->current();
        $profile->update($details, $actor, $this->clock->now());
        $this->audit->record($actor, new AuditEntry('association.update', $details->officialName));
        $this->profiles->save($profile);
    }

    public function replaceCertificate(User $actor, InsuranceCertificate $certificate): void
    {
        $profile = $this->profiles->current();
        $profile->replaceCertificate($certificate, $actor, $this->clock->now());
        $this->audit->record($actor, new AuditEntry('association.insurance', $profile->details()->officialName, [
            'valid_until' => $certificate->validUntil->format('d/m/Y'),
        ]));
        $this->profiles->save($profile);
    }
}
