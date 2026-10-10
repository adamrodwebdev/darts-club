<?php

namespace App\Repository;

use App\Entity\Announcement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /** @return list<Announcement> épinglées d'abord, puis les plus récentes */
    public function feed(int $limit = 50): array
    {
        return $this->findBy([], ['pinned' => 'DESC', 'publishedAt' => 'DESC'], $limit);
    }
}
