<?php

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\User;
use App\Membership\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /** @return list<Membership> paiements chargés, triés par nom */
    public function forSeason(Season $season): array
    {
        /** @var list<Membership> $result */
        $result = $this->createQueryBuilder('m')
            ->leftJoin('m.payments', 'p')->addSelect('p')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->andWhere('m.season = :season')
            ->setParameter('season', $season->label())
            ->orderBy('m.holderName', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneFor(User $user, Season $season): ?Membership
    {
        return $this->findOneBy(['user' => $user, 'season' => $season->label()]);
    }

    /** @return list<Membership> toutes les saisons, la plus récente d'abord */
    public function historyOf(User $user): array
    {
        return $this->findBy(['user' => $user], ['season' => 'DESC']);
    }

    /** @return array<string, true> identifiants (RFC 4122) des adhérents à jour pour la saison */
    public function paidUpUserIds(Season $season): array
    {
        $ids = [];
        foreach ($this->forSeason($season) as $membership) {
            $user = $membership->getUser();
            if (null !== $user && \App\Membership\MembershipStatus::Paid === $membership->status()) {
                $ids[$user->getId()->toRfc4122()] = true;
            }
        }

        return $ids;
    }

    /** Suppression de compte : la ligne comptable reste, sans lien vers le compte. */
    public function detachUser(User $user): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.user', 'NULL')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
