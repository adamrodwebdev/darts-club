<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EveningOrder;
use App\Evening\KitchenStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<EveningOrder>
 */
class EveningOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EveningOrder::class);
    }

    /**
     * Commandes d'une soirée à une étape de la cuisine, dans l'ordre où elles y sont arrivées.
     *
     * @return list<EveningOrder>
     */
    public function atStep(ClubEvent $event, KitchenStatus $status): array
    {
        /** @var list<EveningOrder> $orders */
        $orders = $this->createQueryBuilder('o')
            ->andWhere('o.event = :event')
            ->andWhere('o.kitchenStatus = :status')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('status', $status->value)
            ->orderBy('o.number', 'ASC')
            ->getQuery()
            ->getResult();

        return $orders;
    }

    /**
     * Dernières commandes passées à une étape, les plus récentes d'abord.
     *
     * @param list<KitchenStatus> $statuses
     *
     * @return list<EveningOrder>
     */
    public function latestAt(ClubEvent $event, array $statuses, int $limit): array
    {
        /** @var list<EveningOrder> $orders */
        $orders = $this->createQueryBuilder('o')
            ->andWhere('o.event = :event')
            ->andWhere('o.kitchenStatus IN (:statuses)')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('statuses', array_map(static fn (KitchenStatus $status): string => $status->value, $statuses))
            ->orderBy('o.number', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $orders;
    }

    /** @return list<EveningOrder> les plus récentes d'abord, toutes étapes confondues */
    public function latestFor(ClubEvent $event, int $limit): array
    {
        return $this->findBy(['event' => $event], ['number' => 'DESC'], $limit);
    }
}
