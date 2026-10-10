<?php

namespace App\Repository;

use App\Entity\Broadcast;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Broadcast>
 */
class BroadcastRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Broadcast::class);
    }

    /** @return list<Broadcast> */
    public function latest(int $limit = 50): array
    {
        return $this->findBy([], ['sentAt' => 'DESC'], $limit);
    }
}
