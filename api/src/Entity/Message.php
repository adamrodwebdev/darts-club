<?php

namespace App\Entity;

use App\Messaging\MessageBody;
use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Index(name: 'idx_message_conversation_created', columns: ['conversation_id', 'created_at'])]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    /** Null si l'auteur a supprimé son compte (message anonymisé) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Créé uniquement par Conversation::post(). */
    public function __construct(Conversation $conversation, User $author, MessageBody $body, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->conversation = $conversation;
        $this->author = $author;
        $this->body = $body->value;
        $this->createdAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function body(): MessageBody
    {
        return MessageBody::fromString($this->body);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Version unitaire de MessageRepository::anonymizeAuthor(). */
    public function anonymize(): void
    {
        $this->author = null;
    }

    public function isFrom(User $user): bool
    {
        return null !== $this->author && $this->author->getId()->equals($user->getId());
    }
}
