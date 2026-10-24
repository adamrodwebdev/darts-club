<?php

namespace App\Documents;

use App\Entity\AssociationProfile;
use App\Entity\ClubEvent;
use App\Entity\EventAccounts;

/** Ce qu'un document reprend : la soirée, la fiche de l'association, le bilan et la date du jour. */
final readonly class DocumentContext
{
    public function __construct(
        public ClubEvent $event,
        public AssociationProfile $profile,
        public DocumentFacts $facts,
    ) {
    }

    public function details(): AssociationDetails
    {
        return $this->profile->details();
    }

    public function accounts(): ?EventAccounts
    {
        return $this->facts->accounts;
    }
}
