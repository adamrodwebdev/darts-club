<?php

namespace App\Repository;

use App\Documents\DocumentTemplate;
use App\Entity\ClubEvent;
use App\Entity\EventProcedure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventProcedure>
 */
class EventProcedureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventProcedure::class);
    }

    /** La démarche enregistrée, sinon celle que prévoit le modèle (pas encore enregistrée) */
    public function of(ClubEvent $event, DocumentTemplate $template): EventProcedure
    {
        return $this->findOneBy(['event' => $event, 'type' => $template->type()]) ?? EventProcedure::start($event, $template);
    }

    /** @return array<string, EventProcedure> démarches enregistrées de la soirée, par type */
    public function forEvent(ClubEvent $event): array
    {
        $procedures = [];
        foreach ($this->findBy(['event' => $event]) as $procedure) {
            $procedures[$procedure->type()->value] = $procedure;
        }

        return $procedures;
    }

    /** Enregistre la démarche et ce que la même opération a préparé (journal) */
    public function save(EventProcedure $procedure): void
    {
        $this->getEntityManager()->persist($procedure);
        $this->getEntityManager()->flush();
    }
}
