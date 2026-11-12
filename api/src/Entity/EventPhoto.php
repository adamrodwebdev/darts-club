<?php

declare(strict_types=1);

namespace App\Entity;

use App\ClubEvent\PressDetails;
use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Gallery\GalleryMedia;
use App\Gallery\HiddenOnRequest;
use App\Gallery\MediaKind;
use App\Repository\EventPhotoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Photo de la galerie d'un événement (grande version + vignette en WebP, et en AVIF plus légère
 * une fois que le worker les a produites). Une photo classée parmi les coupures de presse quitte
 * la galerie pour « Dans la presse », avec ses détails (PressDetails).
 */
#[ORM\Entity(repositoryClass: EventPhotoRepository::class)]
class EventPhoto implements GalleryMedia, RecordsDomainEvents
{
    use DomainEventsTrait;
    use HiddenOnRequest;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 80)]
    private string $uploaderName;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    /** Coupure de presse : les quatre détails ci-dessous sont facultatifs, ce drapeau seul la classe */
    #[ORM\Column]
    private bool $pressClipping = false;

    #[ORM\Column(length: PressDetails::OUTLET_MAX, nullable: true)]
    private ?string $pressOutlet = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pressPublishedOn = null;

    #[ORM\Column(length: PressDetails::TITLE_MAX, nullable: true)]
    private ?string $pressTitle = null;

    #[ORM\Column(length: PressDetails::CREDIT_MAX, nullable: true)]
    private ?string $pressCredit = null;

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

    public function kind(): MediaKind
    {
        return MediaKind::Photo;
    }

    public function isVisible(): bool
    {
        return !$this->isHidden();
    }

    public function uploaderName(): string
    {
        return $this->uploaderName;
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

    /**
     * La classe parmi les coupures de presse de l'événement, ou remplace les détails d'une coupure :
     * elle quitte la galerie pour « Dans la presse ».
     */
    public function markAsPressClipping(PressDetails $details): void
    {
        $this->pressClipping = true;
        $this->pressOutlet = $details->outlet;
        $this->pressPublishedOn = $details->publishedOn;
        $this->pressTitle = $details->title;
        $this->pressCredit = $details->credit;
    }

    /** La rend à la galerie : ses détails de presse sont oubliés. */
    public function returnToGallery(): void
    {
        $this->pressClipping = false;
        $this->pressOutlet = $this->pressTitle = $this->pressCredit = null;
        $this->pressPublishedOn = null;
    }

    public function isPressClipping(): bool
    {
        return $this->pressClipping;
    }

    /** Détails de la coupure de presse ; null pour une photo de la galerie */
    public function pressDetails(): ?PressDetails
    {
        if (!$this->pressClipping) {
            return null;
        }

        return new PressDetails(
            outlet: $this->pressOutlet,
            publishedOn: $this->pressPublishedOn,
            title: $this->pressTitle,
            credit: $this->pressCredit,
        );
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
            'hidden' => $this->isHidden(),
            'hiddenBy' => $this->hiddenBy(),
            'press' => $this->pressDetails()?->toArray(),
        ];
    }
}
