<?php

namespace App\Entity;

use App\Notification\Push\PushEndpoint;
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

    public function __construct(User $user, PushEndpoint $endpoint, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->endpoint = $endpoint->url;
        $this->publicKey = $endpoint->publicKey;
        $this->authToken = $endpoint->authToken;
        $this->createdAt = $now;
    }

    /** Même navigateur, autre compte connecté : l'abonnement change de propriétaire (clés renouvelées). */
    public function reassign(User $user, PushEndpoint $endpoint): void
    {
        if ($endpoint->url !== $this->endpoint) {
            throw new \LogicException('Autre navigateur : un nouvel abonnement est attendu.');
        }

        $this->user = $user;
        $this->publicKey = $endpoint->publicKey;
        $this->authToken = $endpoint->authToken;
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

    public function endpoint(): PushEndpoint
    {
        return new PushEndpoint($this->endpoint, $this->publicKey, $this->authToken);
    }
}
