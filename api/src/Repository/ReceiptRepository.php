<?php

namespace App\Repository;

use App\Entity\Receipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Receipt>
 */
class ReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Receipt::class);
    }

    /** @return list<Receipt> justificatifs des soirées terminées avant cette date */
    public function forEventsEndedBefore(\DateTimeImmutable $limit): array
    {
        /** @var list<Receipt> $receipts */
        $receipts = $this->createQueryBuilder('r')
            ->join('r.accounts', 'a')
            ->join('a.event', 'e')
            ->where('e.endsAt < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();

        return $receipts;
    }

    /** Retire les justificatifs et enregistre */
    public function deleteAll(Receipt ...$receipts): void
    {
        foreach ($receipts as $receipt) {
            $this->getEntityManager()->remove($receipt);
        }
        $this->getEntityManager()->flush();
    }
}
