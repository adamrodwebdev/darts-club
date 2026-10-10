<?php

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Abonnement Web Push d'un navigateur. */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::TEXT, unique: true)]
    private string $endpoint;

    #[ORM\Column(length: 255)]
    private string $publicKey;

    #[ORM\Column(length: 255)]
    private string $authToken;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $endpoint, string $publicKey, string $authToken)
    {
        if (!str_starts_with($endpoint, 'https://')) {
            throw new \InvalidArgumentException('Le point de terminaison push doit être en HTTPS.');
        }

        $this->id = Uuid::v7();
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Même navigateur, autre compte connecté : l'abonnement change de propriétaire. */
    public function reassign(User $user, string $publicKey, string $authToken): void
    {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }
}
