<?php

namespace App\Entity;

use App\Messaging\MessageBody;
use App\Repository\EventPostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Post d'un adhérent dans l'archive d'un événement (souvenirs, scores, remerciements…). */
#[ORM\Entity(repositoryClass: EventPostRepository::class)]
class EventPost
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $author,
        MessageBody $body,
        \DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->body = $body->value;
        $this->createdAt = $now;
    }

    public function isWrittenBy(User $user): bool
    {
        return null !== $this->author && $this->author->getId()->equals($user->getId());
    }

    public function getEvent(): ClubEvent
    {
        return $this->event;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
