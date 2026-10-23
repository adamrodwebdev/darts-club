<?php

namespace App\Entity;

use App\Repository\EventPhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Photo de la galerie d'un événement (grande version + vignette WebP). */
#[ORM\Entity(repositoryClass: EventPhotoRepository::class)]
class EventPhoto
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 80)]
    private string $uploaderName;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $uploader,
        #[ORM\Column(length: 32)]
        private string $largeId,
        #[ORM\Column(length: 32)]
        private string $thumbId,
        #[ORM\Column]
        private int $width,
        #[ORM\Column]
        private int $height,
        \DateTimeImmutable $now,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $caption = null,
    ) {
        $this->id = Uuid::v7();
        $this->uploaderName = mb_substr($uploader?->displayName()->value ?? 'Ancien adhérent', 0, 80);
        $this->uploadedAt = $now;
    }

    public function isUploadedBy(User $user): bool
    {
        return null !== $this->uploader && $this->uploader->getId()->equals($user->getId());
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEvent(): ClubEvent
    {
        return $this->event;
    }

    public function largeId(): string
    {
        return $this->largeId;
    }

    public function thumbId(): string
    {
        return $this->thumbId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $base = '/api/event-photos/'.$this->id->toRfc4122();

        return [
            'id' => $this->id->toRfc4122(),
            'thumbUrl' => $base.'/thumb',
            'largeUrl' => $base.'/large',
            'width' => $this->width,
            'height' => $this->height,
            'caption' => $this->caption,
            'uploader' => $this->uploaderName,
            'uploaderId' => $this->uploader?->getId()->toRfc4122(),
            'uploadedAt' => $this->uploadedAt->format(\DATE_ATOM),
        ];
    }
}
