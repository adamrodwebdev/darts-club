<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\TokenSale;
use App\Evening\PaymentMethod;
use App\Membership\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<TokenSale>
 */
class TokenSaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TokenSale::class);
    }

    /** @return list<TokenSale> les plus récentes d'abord */
    public function latestFor(ClubEvent $event, int $limit): array
    {
        return $this->findBy(['event' => $event], ['soldAt' => 'DESC'], $limit);
    }

    public function existsFor(ClubEvent $event): bool
    {
        return null !== $this->findOneBy(['event' => $event]);
    }

    /** @return list<TokenSale> toutes les ventes de la soirée, annulées comprises, dans l'ordre */
    public function forEvent(ClubEvent $event): array
    {
        return $this->findBy(['event' => $event], ['soldAt' => 'ASC']);
    }

    /**
     * Argent encaissé à l'accueil ce soir, ventes annulées exclues (somme en base) ; null tant que
     * l'accueil n'a rien vendu : la soirée se passe alors de jetons.
     */
    public function entranceMoney(ClubEvent $event): ?Money
    {
        $row = $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(CASE WHEN s.cancelledAt IS NULL THEN 1 ELSE 0 END), 0) AS kept', 'COALESCE(SUM(CASE WHEN s.cancelledAt IS NULL THEN s.total ELSE 0 END), 0) AS taken')
            ->andWhere('s.event = :event')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleResult();
        $kept = \is_array($row) && is_numeric($row['kept'] ?? null) ? (int) $row['kept'] : 0;
        $taken = \is_array($row) && is_numeric($row['taken'] ?? null) ? (int) $row['taken'] : 0;

        return 0 === $kept ? null : Money::cents($taken);
    }

    /** Argent encaissé ce soir par moyen de paiement, ventes annulées exclues (somme en base, rien ne dérive) */
    public function takenWith(ClubEvent $event, PaymentMethod $method): Money
    {
        $cents = $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.event = :event')
            ->andWhere('s.method = :method')
            ->andWhere('s.cancelledAt IS NULL')
            ->setParameter('event', $event->getId(), UuidType::NAME)
            ->setParameter('method', $method->value)
            ->getQuery()
            ->getSingleScalarResult();

        return Money::cents(is_numeric($cents) ? (int) $cents : 0);
    }

    public function save(TokenSale $sale): void
    {
        $this->getEntityManager()->persist($sale);
        $this->getEntityManager()->flush();
    }
}
