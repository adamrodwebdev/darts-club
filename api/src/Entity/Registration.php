<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Inscription d'un adhérent à un événement ; créée par ClubEvent::register(). */
#[ORM\Entity]
#[ORM\Table(name: 'event_registration')]
#[ORM\UniqueConstraint(name: 'uniq_registration_event_user', columns: ['event_id', 'user_id'])]
class Registration
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'registrations')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column]
        private \DateTimeImmutable $registeredAt,
    ) {
        $this->id = Uuid::v7();
    }

    public function isFor(User $user): bool
    {
        return $this->user->getId()->equals($user->getId());
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEvent(): ClubEvent
    {
        return $this->event;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
