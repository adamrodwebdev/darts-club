<?php

namespace App\Entity;

use App\ClubEvent\ClubTime;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentType;
use App\Documents\ProcedureContent;
use App\Documents\ProcedureStatus;
use App\Repository\EventProcedureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Démarche d'une soirée (buvette, salle, SACEM…) : où elle en est et ce que le secrétariat a changé
 * dans son document. Elle n'est enregistrée qu'à la première modification ; avant, le document suit
 * entièrement la soirée et la fiche de l'association.
 */
#[ORM\Entity(repositoryClass: EventProcedureRepository::class)]
#[ORM\Table(name: 'event_procedure')]
#[ORM\UniqueConstraint(name: 'uniq_event_procedure_type', columns: ['event_id', 'type'])]
class EventProcedure
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClubEvent $event;

    #[ORM\Column(length: 30, enumType: DocumentType::class)]
    private DocumentType $type;

    #[ORM\Column(length: 20, enumType: ProcedureStatus::class)]
    private ProcedureStatus $status;

    /** @var array<string, string|bool> champs modifiés */
    #[ORM\Column(type: Types::JSON)]
    private array $fieldValues = [];

    /** @var array<string, string> morceaux réécrits, par identifiant */
    #[ORM\Column(type: Types::JSON)]
    private array $rewrites = [];

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentOn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $receivedOn = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    private function __construct(ClubEvent $event, DocumentType $type, ProcedureStatus $status)
    {
        $this->id = Uuid::v7();
        $this->event = $event;
        $this->type = $type;
        $this->status = $status;
    }

    /** Démarche telle que le modèle la prévoit : à faire, ou « pas pour cette soirée » d'office */
    public static function start(ClubEvent $event, DocumentTemplate $template): self
    {
        return new self($event, $template->type(), $template->expectedByDefault() ? ProcedureStatus::Todo : ProcedureStatus::NotNeeded);
    }

    /** Document modifié : une démarche à faire (ou jugée inutile) devient prête */
    public function record(ProcedureContent $content, User $by, \DateTimeImmutable $now): void
    {
        $this->fieldValues = $content->values;
        $this->rewrites = $content->rewrites;
        if (\in_array($this->status, [ProcedureStatus::Todo, ProcedureStatus::NotNeeded], true)) {
            $this->status = ProcedureStatus::Ready;
        }
        $this->touch($by, $now);
    }

    /** Envoyée ou reçue : la date du jour est notée ; revenir en arrière efface les dates */
    public function changeStatus(ProcedureStatus $status, User $by, \DateTimeImmutable $now): void
    {
        $today = $now->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE))->setTime(0, 0);
        if (ProcedureStatus::Sent === $status) {
            $this->sentOn ??= $today;
            $this->receivedOn = null;
        } elseif (ProcedureStatus::Received === $status) {
            $this->receivedOn ??= $today;
        } else {
            $this->sentOn = null;
            $this->receivedOn = null;
        }
        $this->status = $status;
        $this->touch($by, $now);
    }

    public function content(): ProcedureContent
    {
        return new ProcedureContent($this->fieldValues, $this->rewrites);
    }

    public function isEdited(): bool
    {
        return [] !== $this->fieldValues || [] !== $this->rewrites;
    }

    public function type(): DocumentType
    {
        return $this->type;
    }

    public function status(): ProcedureStatus
    {
        return $this->status;
    }

    public function sentOn(): ?\DateTimeImmutable
    {
        return $this->sentOn;
    }

    public function receivedOn(): ?\DateTimeImmutable
    {
        return $this->receivedOn;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function event(): ClubEvent
    {
        return $this->event;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    private function touch(User $by, \DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
        $this->updatedBy = $by;
    }
}
