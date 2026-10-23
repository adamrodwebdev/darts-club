<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return list<Notification> */
    public function latestFor(User $user, bool $unreadOnly = false, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit);

        if ($unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        /** @var list<Notification> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function countUnread(User $user): int
    {
        return $this->count(['recipient' => $user, 'readAt' => null]);
    }

    public function markAllRead(User $user, \DateTimeImmutable $now): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->andWhere('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
