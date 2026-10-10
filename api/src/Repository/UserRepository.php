<?php

namespace App\Repository;

use App\Entity\User;
use App\ValueObject\EmailAddress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(EmailAddress $email): ?User
    {
        return $this->findOneBy(['email' => $email->value]);
    }

    /** @return list<User> comptes vérifiés et non suspendus */
    public function findActive(): array
    {
        return $this->findBy(['verified' => true, 'suspendedAt' => null]);
    }

    /** @return list<string> identifiants RFC 4122 des comptes actifs */
    public function findActiveIds(): array
    {
        /** @var list<array{id: \Symfony\Component\Uid\Uuid}> $rows */
        $rows = $this->createQueryBuilder('u')
            ->select('u.id')
            ->andWhere('u.verified = true')
            ->andWhere('u.suspendedAt IS NULL')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): string => $row['id']->toRfc4122(), $rows);
    }

    /** Re-hash automatique quand l'algorithme ou son coût évolue. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->changePassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
