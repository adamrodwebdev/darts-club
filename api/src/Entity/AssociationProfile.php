<?php

namespace App\Entity;

use App\Documents\AssociationDetails;
use App\Documents\InsuranceCertificate;
use App\Repository\AssociationProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Fiche de l'association (une seule) : reprise dans les documents des soirées, réservée au bureau.
 * L'adresse du siège n'est jamais affichée sur le site public (seule « Bellevesvre » l'est).
 */
#[ORM\Entity(repositoryClass: AssociationProfileRepository::class)]
#[ORM\Table(name: 'association_profile')]
class AssociationProfile
{
    public const DEFAULT_NAME = 'Skull Darts 71';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $officialName = self::DEFAULT_NAME;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $rnaNumber = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $seatAddress = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $presidentName = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $secretaryName = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $treasurerName = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $insurerName = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $insurancePolicy = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $certificateFileId = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $certificateValidUntil = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    private function __construct()
    {
        $this->id = Uuid::v7();
    }

    /** Fiche vierge, créée à la première saisie */
    public static function blank(): self
    {
        return new self();
    }

    public function update(AssociationDetails $details, User $by, \DateTimeImmutable $now): void
    {
        $this->officialName = $details->officialName;
        $this->rnaNumber = $details->rnaNumber;
        $this->siret = $details->siret;
        $this->seatAddress = $details->seatAddress;
        $this->presidentName = $details->presidentName;
        $this->secretaryName = $details->secretaryName;
        $this->treasurerName = $details->treasurerName;
        $this->insurerName = $details->insurerName;
        $this->insurancePolicy = $details->insurancePolicy;
        $this->touch($by, $now);
    }

    public function replaceCertificate(InsuranceCertificate $certificate, User $by, \DateTimeImmutable $now): void
    {
        $this->certificateFileId = $certificate->fileId;
        $this->certificateValidUntil = $certificate->validUntil;
        $this->touch($by, $now);
    }

    public function details(): AssociationDetails
    {
        return new AssociationDetails(
            $this->officialName,
            rnaNumber: $this->rnaNumber,
            siret: $this->siret,
            seatAddress: $this->seatAddress,
            presidentName: $this->presidentName,
            secretaryName: $this->secretaryName,
            treasurerName: $this->treasurerName,
            insurerName: $this->insurerName,
            insurancePolicy: $this->insurancePolicy,
        );
    }

    public function certificate(): ?InsuranceCertificate
    {
        return null === $this->certificateFileId || null === $this->certificateValidUntil
            ? null
            : new InsuranceCertificate($this->certificateFileId, $this->certificateValidUntil);
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updatedBy(): ?User
    {
        return $this->updatedBy;
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
