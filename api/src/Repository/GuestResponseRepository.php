<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\GuestResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuestResponse>
 */
class GuestResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuestResponse::class);
    }

    public function findByToken(string $token): ?GuestResponse
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }
        $response = $this->findOneBy(['tokenHash' => GuestResponse::hashToken($token)]);

        return true === $response?->matchesToken($token) ? $response : null;
    }

    /**
     * Conservation limitée : réponses jamais confirmées après 48 h,
     * toutes les réponses 30 jours après la fin de l'événement.
     *
     * @return int nombre de réponses supprimées
     */
    public function purge(\DateTimeImmutable $now): int
    {
        $unconfirmed = $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.answer IS NULL AND r.tokenIssuedAt < :expired')
            ->setParameter('expired', $now->sub(new \DateInterval(GuestResponse::CONFIRMATION_DELAY)))
            ->getQuery()
            ->execute();

        $old = $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.event IN (SELECT e.id FROM '.ClubEvent::class.' e WHERE e.endsAt < :limit)')
            ->setParameter('limit', $now->sub(new \DateInterval('P30D')), 'datetimetz_immutable')
            ->getQuery()
            ->execute();

        return (\is_int($unconfirmed) ? $unconfirmed : 0) + (\is_int($old) ? $old : 0);
    }
}
