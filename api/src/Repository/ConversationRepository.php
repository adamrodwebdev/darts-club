<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use App\Messaging\Mailbox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findDirectBetween(User $a, User $b): ?Conversation
    {
        $result = $this->createQueryBuilder('c')
            ->join('c.participants', 'pa', 'WITH', 'pa.user = :a')
            ->join('c.participants', 'pb', 'WITH', 'pb.user = :b')
            ->andWhere('c.mailbox IS NULL')
            ->setParameter('a', $a->getId(), 'uuid')
            ->setParameter('b', $b->getId(), 'uuid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Conversation ? $result : null;
    }

    /**
     * Fils directs où l'adhérent participe, fils qu'il a ouverts vers le bureau, plus ceux des boîtes
     * du bureau qu'il gère aujourd'hui (MailboxAccess::canAccess).
     *
     * @param list<Mailbox> $managedMailboxes
     *
     * @return list<Conversation>
     */
    public function findVisibleTo(User $user, array $managedMailboxes, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.participants', 'p', 'WITH', 'p.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('c.lastMessageAt', 'DESC')
            ->setMaxResults($limit);

        $visible = '(c.mailbox IS NULL AND p.id IS NOT NULL) OR c.requester = :user';
        if ([] === $managedMailboxes) {
            $qb->andWhere($visible);
        } else {
            $qb->andWhere($visible.' OR c.mailbox IN (:mailboxes)')
                ->setParameter('mailboxes', array_map(static fn (Mailbox $m): string => $m->value, $managedMailboxes));
        }

        /** @var list<Conversation> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
