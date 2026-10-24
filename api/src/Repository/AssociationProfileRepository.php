<?php

namespace App\Repository;

use App\Entity\AssociationProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssociationProfile>
 */
class AssociationProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssociationProfile::class);
    }

    /** La fiche de l'association, vierge tant que personne ne l'a remplie */
    public function current(): AssociationProfile
    {
        return $this->findOneBy([]) ?? AssociationProfile::blank();
    }

    public function save(AssociationProfile $profile): void
    {
        $this->getEntityManager()->persist($profile);
        $this->getEntityManager()->flush();
    }
}
