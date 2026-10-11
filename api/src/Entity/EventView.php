<?php

namespace App\Entity;

use App\Repository\EventViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Personne unique ayant vu l'annonce d'un événement (mesure d'audience).
 * Aucune donnée identifiante : seulement une empreinte HMAC dont la clé
 * (propre à l'événement) est détruite 30 jours après celui-ci.
 * Écrit par EventAudience (insertion « une seule fois » en SQL).
 */
#[ORM\Entity(repositoryClass: EventViewRepository::class)]
#[ORM\Table(name: 'event_view')]
#[ORM\UniqueConstraint(name: 'uniq_event_view_visitor', columns: ['event_id', 'visitor_key'])]
class EventView
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        #[ORM\Column(length: 64)]
        private string $visitorKey,
        /** Adhérent connecté (compte exact) ou visiteur anonyme (estimation) */
        #[ORM\Column]
        private bool $member,
        /** Jour de la première vue (heure de Paris) */
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $firstSeenOn,
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEvent(): ClubEvent
    {
        return $this->event;
    }

    public function visitorKey(): string
    {
        return $this->visitorKey;
    }

    public function isMember(): bool
    {
        return $this->member;
    }

    public function firstSeenOn(): \DateTimeImmutable
    {
        return $this->firstSeenOn;
    }
}
