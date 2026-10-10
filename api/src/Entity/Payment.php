<?php

namespace App\Entity;

use App\Membership\Money;
use App\Membership\PaymentMethod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Encaissement d'une cotisation ; créé uniquement par Membership::recordPayment(). */
#[ORM\Entity]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Membership $membership;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 20, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference;

    /** Trésorier ayant saisi le paiement */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recordedBy;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $cancelReason = null;

    public function __construct(Membership $membership, Money $amount, PaymentMethod $method, \DateTimeImmutable $paidAt, ?string $reference, ?User $recordedBy)
    {
        $this->id = Uuid::v7();
        $this->membership = $membership;
        $this->amount = $amount->cents;
        $this->method = $method;
        $this->paidAt = $paidAt;
        $reference = null !== $reference ? trim($reference) : '';
        $this->reference = '' !== $reference ? mb_substr($reference, 0, 80) : null;
        $this->recordedBy = $recordedBy;
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function cancel(string $reason): void
    {
        if ($this->isCancelled()) {
            throw new \LogicException('Paiement déjà annulé.');
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \InvalidArgumentException('Le motif d\'annulation est obligatoire.');
        }

        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancelReason = mb_substr($reason, 0, 200);
    }

    public function isCancelled(): bool
    {
        return null !== $this->cancelledAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMembership(): Membership
    {
        return $this->membership;
    }

    public function amount(): Money
    {
        return Money::cents($this->amount);
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function paidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function recordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }
}
