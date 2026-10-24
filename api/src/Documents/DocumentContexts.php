<?php

namespace App\Documents;

use App\Entity\ClubEvent;
use App\Repository\AssociationProfileRepository;
use App\Repository\EventAccountsRepository;
use Symfony\Component\Clock\ClockInterface;

/** Rassemble ce qu'un document reprend d'une soirée : fiche de l'association, bilan, date du jour. */
final class DocumentContexts
{
    public function __construct(
        private readonly AssociationProfileRepository $profiles,
        private readonly EventAccountsRepository $books,
        private readonly ClockInterface $clock,
    ) {
    }

    public function for(ClubEvent $event): DocumentContext
    {
        return new DocumentContext($event, $this->profiles->current(), new DocumentFacts(
            $this->clock->now(),
            $this->books->findForEvent($event),
            $this->books->temporaryBarsBefore($event),
        ));
    }
}
