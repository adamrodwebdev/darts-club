<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EventVideo;
use App\Enum\VideoStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<EventVideo>
 */
class EventVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventVideo::class);
    }

    /** @return list<EventVideo> */
    public function forEvent(ClubEvent $event): array
    {
        return $this->findBy(['event' => $event], ['uploadedAt' => 'ASC']);
    }

    /** Lu en base, pas dans les entités chargées : une vidéo supprimée pendant sa conversion */
    public function exists(Uuid $id): bool
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.id = :id')
            ->setParameter('id', $id, UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** Vidéos qui comptent dans la limite d'un événement : toutes sauf les échecs */
    public function countKeptForEvent(ClubEvent $event): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.event = :event')
            ->andWhere('v.status != :failed')
            ->setParameter('event', $event)
            ->setParameter('failed', VideoStatus::Failed)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Envois abandonnés avant la date donnée, ou échecs plus anciens qu'elle.
     *
     * @return list<EventVideo>
     */
    public function staleBefore(VideoStatus $status, \DateTimeImmutable $before): array
    {
        /** @var list<EventVideo> $result */
        $result = $this->createQueryBuilder('v')
            ->where('v.status = :status')
            ->andWhere('v.uploadedAt < :before')
            ->setParameter('status', $status)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
