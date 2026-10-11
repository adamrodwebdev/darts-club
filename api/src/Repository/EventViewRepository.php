<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EventView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<EventView>
 */
class EventViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventView::class);
    }

    /**
     * Enregistre la première vue d'une personne ; les suivantes sont ignorées
     * par la base (contrainte d'unicité), sans erreur même en cas de requêtes simultanées.
     */
    public function recordOnce(ClubEvent $event, string $visitorKey, bool $member, \DateTimeImmutable $day): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO event_view (id, event_id, visitor_key, member, first_seen_on) VALUES (?, ?, ?, ?, ?) ON CONFLICT (event_id, visitor_key) DO NOTHING',
            [Uuid::v7(), $event->getId(), $visitorKey, $member, $day],
            [UuidType::NAME, UuidType::NAME, Types::STRING, Types::BOOLEAN, Types::DATE_IMMUTABLE],
        );
    }

    /** @return list<array{day: string, members: int, visitors: int}> nouvelles personnes par jour */
    public function byDay(ClubEvent $event): array
    {
        /** @var list<array{day: \DateTimeImmutable, member: bool, people: int|string}> $rows */
        $rows = $this->createQueryBuilder('v')
            ->select('v.firstSeenOn AS day, v.member AS member, COUNT(v.id) AS people')
            ->andWhere('v.event = :event')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->groupBy('v.firstSeenOn, v.member')
            ->orderBy('v.firstSeenOn', 'ASC')
            ->getQuery()
            ->getResult();

        $days = [];
        foreach ($rows as $row) {
            $day = $row['day']->format('Y-m-d');
            $days[$day] ??= ['day' => $day, 'members' => 0, 'visitors' => 0];
            $days[$day][$row['member'] ? 'members' : 'visitors'] += (int) $row['people'];
        }

        return array_values($days);
    }

    /** Détail supprimé (les totaux sont archivés sur l'événement avant) */
    public function deleteFor(ClubEvent $event): void
    {
        $this->createQueryBuilder('v')
            ->delete()
            ->andWhere('v.event = :event')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->getQuery()
            ->execute();
    }
}
