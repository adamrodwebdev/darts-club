<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Page de messages, du plus récent au plus ancien (UUID v7 = ordre chronologique).
     *
     * @return list<Message>
     */
    public function page(Conversation $conversation, ?Uuid $before = null, int $limit = 30): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation->getId(), 'uuid')
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $before) {
            $qb->andWhere('m.id < :before')->setParameter('before', $before, 'uuid');
        }

        /** @var list<Message> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function latest(Conversation $conversation): ?Message
    {
        return $this->page($conversation, limit: 1)[0] ?? null;
    }

    public function countUnread(Conversation $conversation, User $reader): int
    {
        $lastReadAt = $conversation->participantFor($reader)?->getLastReadAt();

        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.author IS NULL OR m.author != :reader')
            ->setParameter('conversation', $conversation->getId(), 'uuid')
            ->setParameter('reader', $reader->getId(), 'uuid');

        if (null !== $lastReadAt) {
            $qb->andWhere('m.createdAt > :lastReadAt')->setParameter('lastReadAt', $lastReadAt, 'datetime_immutable');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Anonymisation à la suppression d'un compte (RGPD) : les fils restent lisibles par les autres. */
    public function anonymizeAuthor(User $author): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.author', 'NULL')
            ->andWhere('m.author = :author')
            ->setParameter('author', $author->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
