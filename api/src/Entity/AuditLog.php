<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Journal des actions d'administration (rôles, suspensions, diffusions…).
 * En écriture seule : aucune modification ni suppression via l'application.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class, readOnly: true)]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        /** Nom de l'auteur figé au moment de l'action (survit à la suppression du compte) */
        #[ORM\Column(length: 80)]
        private string $actorName,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $actorId,
        #[ORM\Column(length: 40)]
        private string $action,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $target,
        #[ORM\Column(type: Types::JSON)]
        private array $details = [],
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'at' => $this->createdAt->format(\DATE_ATOM),
            'actor' => $this->actorName,
            'actorId' => $this->actorId?->toRfc4122(),
            'action' => $this->action,
            'target' => $this->target,
            'details' => $this->details,
        ];
    }
}
