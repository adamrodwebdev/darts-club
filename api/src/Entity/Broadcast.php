<?php

namespace App\Entity;

use App\Admin\BroadcastLabel;
use App\Repository\BroadcastRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Historique des messages labellisés envoyés par le président. */
#[ORM\Entity(repositoryClass: BroadcastRepository::class, readOnly: true)]
class Broadcast
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    public function __construct(
        #[ORM\Column(length: 20, enumType: BroadcastLabel::class)]
        private BroadcastLabel $label,
        #[ORM\Column(length: 120)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
        #[ORM\Column(length: 80)]
        private string $authorName,
        /** « all » ou nombre d'adhérents sélectionnés */
        #[ORM\Column]
        private bool $toAllMembers,
        #[ORM\Column]
        private int $recipientCount,
        \DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->sentAt = $now;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'label' => $this->label->value,
            'labelText' => $this->label->category()->label(),
            'title' => $this->title,
            'body' => $this->body,
            'author' => $this->authorName,
            'toAllMembers' => $this->toAllMembers,
            'recipientCount' => $this->recipientCount,
            'sentAt' => $this->sentAt->format(\DATE_ATOM),
        ];
    }
}
