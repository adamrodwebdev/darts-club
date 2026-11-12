<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventPhoto>
 */
class EventPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventPhoto::class);
    }

    /**
     * Dans l'ordre d'arrivée. Les photos d'un même envoi ont la même heure : l'identifiant (UUID v7,
     * croissant) les départage, toujours dans le même ordre.
     *
     * @return list<EventPhoto>
     */
    public function forEvent(ClubEvent $event): array
    {
        return $this->findBy(['event' => $event], ['uploadedAt' => 'ASC', 'id' => 'ASC']);
    }

    public function countForEvent(ClubEvent $event): int
    {
        return $this->count(['event' => $event]);
    }
}
