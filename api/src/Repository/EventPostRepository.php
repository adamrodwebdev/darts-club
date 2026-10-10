<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EventPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventPost>
 */
class EventPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventPost::class);
    }

    /** @return list<EventPost> */
    public function forEvent(ClubEvent $event): array
    {
        return $this->findBy(['event' => $event], ['createdAt' => 'ASC'], 500);
    }
}
