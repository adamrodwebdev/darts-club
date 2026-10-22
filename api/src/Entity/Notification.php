<?php

namespace App\Entity;

use App\Notification\Category;
use App\Notification\NotificationContent;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Entrée du centre de notifications d'un adhérent. */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(name: 'idx_notification_recipient_created', columns: ['recipient_id', 'created_at'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 20, enumType: Category::class)]
    private Category $category;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(User $recipient, NotificationContent $content, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->recipient = $recipient;
        $this->category = $content->category;
        $this->title = $content->title;
        $this->body = $content->body;
        $this->link = $content->link;
        $this->createdAt = $now;
    }

    public function markRead(\DateTimeImmutable $now): void
    {
        $this->readAt ??= $now;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function isFor(User $user): bool
    {
        return $this->recipient->getId()->equals($user->getId());
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    /** @return array{id: string, category: string, label: string, title: string, body: string, link: ?string, createdAt: string, read: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'category' => $this->category->value,
            'label' => $this->category->label(),
            'title' => $this->title,
            'body' => $this->body,
            'link' => $this->link,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'read' => $this->isRead(),
        ];
    }
}
