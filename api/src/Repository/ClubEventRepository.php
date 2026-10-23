<?php

namespace App\Repository;

use App\ClubEvent\ArchiveSearch;
use App\ClubEvent\EventStatus;
use App\Entity\ClubEvent;
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

    /** @return list<ClubEvent> événements ouverts au public, à venir ou en cours, du plus proche au plus lointain */
    public function upcomingPublic(\DateTimeImmutable $now, int $limit = 20): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.public = true AND e.endsAt >= :now')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('e.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<ClubEvent> événements publics terminés (sauf annulés), du plus récent au plus ancien */
    public function pastPublic(\DateTimeImmutable $now, int $limit = 24): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.public = true AND e.endsAt < :now AND e.status <> :cancelled')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->setParameter('cancelled', EventStatus::Cancelled)
            ->orderBy('e.startsAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<ClubEvent> archives, les plus récentes d'abord */
    public function past(\DateTimeImmutable $now, ArchiveSearch $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.endsAt < :now')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('e.startsAt', 'DESC')
            ->setMaxResults($search->limit);

        if (null !== $search->season) {
            $qb->andWhere('e.startsAt >= :from AND e.startsAt <= :to')
                ->setParameter('from', $search->season->startsAt(), 'datetimetz_immutable')
                ->setParameter('to', $search->season->endsAt(), 'datetimetz_immutable');
        }
        if ('' !== $search->query) {
            $qb->andWhere('LOWER(e.title) LIKE :q OR LOWER(e.location) LIKE :q')
                ->setParameter('q', '%'.addcslashes(mb_strtolower($search->query), '%_\\').'%');
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

    /** @return list<ClubEvent> événements terminés dont la clé d'audience existe encore */
    public function withOpenAudienceEndedBefore(\DateTimeImmutable $limit): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.audienceKey IS NOT NULL AND e.endsAt < :limit')
            ->setParameter('limit', $limit, 'datetimetz_immutable')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<ClubEvent> événements anciens dont le détail d'audience n'est pas encore résumé */
    public function withAudienceDetailEndedBefore(\DateTimeImmutable $limit): array
    {
        /** @var list<ClubEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.archivedAudience IS NULL AND e.endsAt < :limit')
            ->andWhere('EXISTS (SELECT 1 FROM '.\App\Entity\EventView::class.' v WHERE v.event = e)')
            ->setParameter('limit', $limit, 'datetimetz_immutable')
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
