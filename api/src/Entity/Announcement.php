<?php

namespace App\Entity;

use App\Announcement\AnnouncementPublished;
use App\Announcement\AnnouncementText;
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

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: AnnouncementText::TITLE_MAX)]
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

    private function __construct(User $author, AnnouncementText $text, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->authorName = mb_substr($author->displayName()->value, 0, 80);
        $this->publishedAt = $now;
        $this->write($text);
    }

    /** Publication (non épinglée : pin() ensuite si besoin) ; les adhérents sont prévenus. */
    public static function publish(User $author, AnnouncementText $text, \DateTimeImmutable $now): self
    {
        $announcement = new self($author, $text, $now);
        $announcement->recordThat(new AnnouncementPublished($announcement->id));

        return $announcement;
    }

    /** Correction sans nouvelle notification. */
    public function edit(AnnouncementText $text, \DateTimeImmutable $now): void
    {
        $this->write($text);
        $this->editedAt = $now;
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

    private function write(AnnouncementText $text): void
    {
        $this->title = $text->title;
        $this->body = $text->body;
    }
}
