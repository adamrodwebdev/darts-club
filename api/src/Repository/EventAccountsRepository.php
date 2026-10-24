<?php

namespace App\Repository;

use App\ClubEvent\ClubTime;
use App\Entity\ClubEvent;
use App\Entity\EventAccounts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventAccounts>
 */
class EventAccountsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventAccounts::class);
    }

    public function findForEvent(ClubEvent $event): ?EventAccounts
    {
        return $this->findOneBy(['event' => $event]);
    }

    /** Enregistre le bilan et ce que la même opération a préparé (journal) */
    public function save(EventAccounts $accounts): void
    {
        $this->getEntityManager()->persist($accounts);
        $this->getEntityManager()->flush();
    }

    /** Soirées de l'année civile (heure de Paris) comptées comme buvette temporaire */
    public function temporaryBarsIn(int $year): int
    {
        return $this->countFlagged('temporaryBar', $year);
    }

    /** Soirées de l'année civile comptées comme manifestation de soutien exonérée */
    public function taxExemptEventsIn(int $year): int
    {
        return $this->countFlagged('taxExempt', $year);
    }

    private function countFlagged(string $flag, int $year): int
    {
        $start = new \DateTimeImmutable(\sprintf('%d-01-01 00:00', $year), new \DateTimeZone(ClubTime::TIMEZONE));

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.event', 'e')
            ->where(\sprintf('a.%s = true', $flag))
            ->andWhere('e.startsAt >= :start AND e.startsAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $start->modify('+1 year'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
