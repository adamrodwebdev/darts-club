<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Membership\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClubEvent>
 */
class ClubEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubEvent::class);
    }

    /** @return list<ClubEvent> à venir ou en cours, du plus proche au plus lointain */
    public function upcoming(\DateTimeImmutable $now, int $limit = 100): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.endsAt >= :now')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('e.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<ClubEvent> archives, les plus récentes d'abord */
    public function past(\DateTimeImmutable $now, ?Season $season = null, string $query = '', int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.endsAt < :now')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('e.startsAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $season) {
            $qb->andWhere('e.startsAt >= :from AND e.startsAt <= :to')
                ->setParameter('from', $season->startsAt(), 'datetimetz_immutable')
                ->setParameter('to', $season->endsAt(), 'datetimetz_immutable');
        }
        if ('' !== trim($query)) {
            $qb->andWhere('LOWER(e.title) LIKE :q OR LOWER(e.location) LIKE :q')
                ->setParameter('q', '%'.addcslashes(mb_strtolower(trim($query)), '%_\\').'%');
        }

        /** @var list<ClubEvent> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /** @return list<ClubEvent> fenêtre utile pour un abonnement calendrier */
    public function forCalendarFeed(\DateTimeImmutable $now): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.startsAt >= :from')
            ->setParameter('from', $now->sub(new \DateInterval('P90D')), 'datetimetz_immutable')
            ->orderBy('e.startsAt', 'ASC')
            ->setMaxResults(500)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<ClubEvent> candidats aux rappels (début dans les 8 prochains jours) */
    public function startingWithinDays(\DateTimeImmutable $now, int $days): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.startsAt > :now AND e.startsAt <= :until')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->setParameter('until', $now->add(new \DateInterval('P'.$days.'D')), 'datetimetz_immutable')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
