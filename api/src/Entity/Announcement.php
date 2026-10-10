<?php

namespace App\Entity;

use App\Announcement\AnnouncementPublished;
use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Repository\AnnouncementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Annonce du canal d'informations du club (texte brut, épinglable). */
#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
#[ORM\Index(name: 'idx_announcement_feed', columns: ['pinned', 'published_at'])]
class Announcement implements RecordsDomainEvents
{
    use DomainEventsTrait;

    public const TITLE_MAX = 120;
    public const BODY_MAX = 5000;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: self::TITLE_MAX)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column]
    private bool $pinned = false;

    #[ORM\Column(length: 80)]
    private string $authorName;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    private function __construct(User $author, string $title, string $body, bool $pinned)
    {
        $this->id = Uuid::v7();
        $this->authorName = mb_substr($author->displayName()->value, 0, 80);
        $this->publishedAt = new \DateTimeImmutable();
        $this->pinned = $pinned;
        $this->write($title, $body);
    }

    public static function publish(User $author, string $title, string $body, bool $pinned = false): self
    {
        $announcement = new self($author, $title, $body, $pinned);
        $announcement->recordThat(new AnnouncementPublished($announcement->id));

        return $announcement;
    }

    /** Correction sans nouvelle notification. */
    public function edit(string $title, string $body): void
    {
        $this->write($title, $body);
        $this->editedAt = new \DateTimeImmutable();
    }

    public function pin(): void
    {
        $this->pinned = true;
    }

    public function unpin(): void
    {
        $this->pinned = false;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'title' => $this->title,
            'body' => $this->body,
            'pinned' => $this->pinned,
            'author' => $this->authorName,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
            'editedAt' => $this->editedAt?->format(\DATE_ATOM),
        ];
    }

    private function write(string $title, string $body): void
    {
        $title = trim($title);
        $body = trim(str_replace("\r\n", "\n", $body));

        if ('' === $title || mb_strlen($title) > self::TITLE_MAX) {
            throw new \InvalidArgumentException(\sprintf('Le titre doit faire entre 1 et %d caractères.', self::TITLE_MAX));
        }
        if ('' === $body || mb_strlen($body) > self::BODY_MAX) {
            throw new \InvalidArgumentException(\sprintf('Le texte doit faire entre 1 et %d caractères.', self::BODY_MAX));
        }

        $this->title = $title;
        $this->body = $body;
    }
}
