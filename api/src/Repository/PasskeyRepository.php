<?php

namespace App\Repository;

use App\Entity\Passkey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Passkey>
 */
class PasskeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Passkey::class);
    }

    /** @param string $credentialId identifiant base64url, tel que le navigateur le renvoie */
    public function findOneByCredentialId(string $credentialId): ?Passkey
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }
}
