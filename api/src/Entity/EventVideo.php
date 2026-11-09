<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VideoFailure;
use App\Enum\VideoStatus;
use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Gallery\GalleryException;
use App\Gallery\GalleryMedia;
use App\Gallery\HiddenOnRequest;
use App\Gallery\MediaKind;
use App\Gallery\UploadDeclaration;
use App\Gallery\UploadOffsetMismatch;
use App\Media\StoredVideo;
use App\Repository\EventVideoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Vidéo courte de la galerie d'un événement. Envoyée par morceaux (l'envoi reprend après une
 * coupure), convertie par le worker en MP4 H.264 lisible partout, puis en AV1 plus léger pour les
 * navigateurs qui le décodent, avec une image d'aperçu ; lue par les membres validés.
 */
#[ORM\Entity(repositoryClass: EventVideoRepository::class)]
class EventVideo implements GalleryMedia, RecordsDomainEvents
{
    use DomainEventsTrait;
    use HiddenOnRequest;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 80)]
    private string $uploaderName;

    #[ORM\Column(length: 120)]
    private string $fileName;

    /** Taille annoncée du fichier envoyé, en octets */
    #[ORM\Column]
    private int $declaredSize;

    #[ORM\Column]
    private int $receivedBytes = 0;

    #[ORM\Column(length: 16, enumType: VideoStatus::class)]
    private VideoStatus $status = VideoStatus::Uploading;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    /** Durée annoncée par l'appareil, puis mesurée par le serveur */
    #[ORM\Column]
    private int $durationMs;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    /** MP4 H.264 : lu par tous les navigateurs */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $fileId = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    /** MP4 AV1 : environ deux fois plus léger, proposé en premier aux navigateurs qui le décodent */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $av1FileId = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $posterId = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $posterAvifId = null;

    #[ORM\Column(length: 16, nullable: true, enumType: VideoFailure::class)]
    private ?VideoFailure $failure = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $uploader,
        UploadDeclaration $declaration,
        \DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->uploaderName = mb_substr($uploader?->displayName()->value ?? 'Ancien adhérent', 0, 80);
        $this->fileName = $declaration->fileName;
        $this->declaredSize = $declaration->size;
        $this->durationMs = $declaration->durationMs;
        $this->uploadedAt = $now;
    }

    /**
     * Un morceau peut s'ajouter : envoi en cours, et il commence là où le précédent s'est arrêté.
     *
     * @throws UploadOffsetMismatch
     */
    public function assertAccepts(int $offset): void
    {
        if (VideoStatus::Uploading !== $this->status) {
            throw new GalleryException('Cet envoi est déjà terminé.');
        }
        if ($offset !== $this->receivedBytes) {
            throw new UploadOffsetMismatch($this->receivedBytes);
        }
    }

    /** Octets que le prochain morceau peut encore apporter */
    public function remainingBytes(): int
    {
        return $this->declaredSize - $this->receivedBytes;
    }

    /** Morceau écrit : l'envoi avance, puis passe en conversion une fois complet. */
    public function receive(int $bytes): void
    {
        if (VideoStatus::Uploading !== $this->status || $bytes < 1 || $bytes > $this->remainingBytes()) {
            throw new \LogicException('Morceau incohérent avec l\'envoi en cours.');
        }

        $this->receivedBytes += $bytes;
        if (0 === $this->remainingBytes()) {
            $this->status = VideoStatus::Processing;
        }
    }

    /** Convertie en H.264 : lisible par les membres. */
    public function publish(StoredVideo $stored): void
    {
        if (VideoStatus::Processing !== $this->status) {
            throw new \LogicException('Seule une vidéo en conversion peut être publiée.');
        }

        $this->status = VideoStatus::Ready;
        $this->fileId = $stored->fileId;
        $this->fileSize = $stored->fileSize;
        $this->posterId = $stored->poster->webpId;
        $this->posterAvifId = $stored->poster->avifId;
        $this->width = $stored->probe->width;
        $this->height = $stored->probe->height;
        $this->durationMs = $stored->probe->durationMs;
    }

    /** Version AV1 ajoutée après coup : le H.264 reste pour les autres navigateurs. */
    public function attachAv1(string $fileId): void
    {
        if (!$this->isReady() || null !== $this->av1FileId) {
            throw new \LogicException('La version AV1 s\'ajoute une fois, à une vidéo publiée.');
        }

        $this->av1FileId = $fileId;
    }

    public function fail(VideoFailure $failure): void
    {
        if (VideoStatus::Ready === $this->status) {
            throw new \LogicException('Une vidéo publiée ne peut plus échouer.');
        }

        $this->status = VideoStatus::Failed;
        $this->failure = $failure;
    }

    public function status(): VideoStatus
    {
        return $this->status;
    }

    public function isReady(): bool
    {
        return VideoStatus::Ready === $this->status;
    }

    public function isVisible(): bool
    {
        return $this->isReady() && !$this->isHidden();
    }

    public function kind(): MediaKind
    {
        return MediaKind::Video;
    }

    public function isUploadedBy(User $user): bool
    {
        return null !== $this->uploader && $this->uploader->getId()->equals($user->getId());
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

    public function fileId(): ?string
    {
        return $this->fileId;
    }

    public function fileSize(): ?int
    {
        return $this->fileSize;
    }

    public function av1FileId(): ?string
    {
        return $this->av1FileId;
    }

    public function posterId(): ?string
    {
        return $this->posterId;
    }

    public function posterAvifId(): ?string
    {
        return $this->posterAvifId;
    }

    /** @return list<string> fichiers rangés de la vidéo, à supprimer avec elle */
    public function storedFiles(): array
    {
        return array_values(array_filter([$this->fileId, $this->av1FileId, $this->posterId, $this->posterAvifId], static fn (?string $id): bool => null !== $id));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $base = '/api/event-videos/'.$this->id->toRfc4122();

        return [
            'id' => $this->id->toRfc4122(),
            'status' => $this->status->value,
            'fileName' => $this->fileName,
            'size' => $this->declaredSize,
            'received' => $this->receivedBytes,
            'durationMs' => $this->durationMs,
            'width' => $this->width,
            'height' => $this->height,
            'videoUrl' => $this->isReady() ? $base.'/file' : null,
            'av1Url' => $this->isReady() && null !== $this->av1FileId ? $base.'/file/av1' : null,
            'posterUrl' => $this->isReady() ? $base.'/poster' : null,
            'failure' => $this->failure?->value,
            'hidden' => $this->isHidden(),
            'hiddenBy' => $this->hiddenBy(),
            'uploader' => $this->uploaderName,
            'uploaderId' => $this->uploader?->getId()->toRfc4122(),
            'uploadedAt' => $this->uploadedAt->format(\DATE_ATOM),
        ];
    }
}
