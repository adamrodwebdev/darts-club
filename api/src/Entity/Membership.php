<?php

namespace App\Entity;

use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Membership\MembershipPaid;
use App\Membership\MembershipStatus;
use App\Membership\Money;
use App\Membership\PaymentMethod;
use App\Membership\Season;
use App\Repository\MembershipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Cotisation d'un adhérent pour une saison. Les paiements ne sont jamais
 * supprimés : une erreur se corrige par annulation (traçabilité comptable).
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_season', columns: ['user_id', 'season'])]
#[ORM\Index(name: 'idx_membership_season', columns: ['season'])]
class Membership implements RecordsDomainEvents
{
    use DomainEventsTrait;

    private const REMINDER_INTERVAL = 'P7D';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** Null si le compte a été supprimé : la ligne comptable est conservée */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    /** Nom au moment de l'ouverture, conservé pour la comptabilité */
    #[ORM\Column(length: 80)]
    private string $holderName;

    #[ORM\Column(length: 9)]
    private string $season;

    #[ORM\Column]
    private int $amountDue;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'membership', cascade: ['persist'])]
    #[ORM\OrderBy(['paidAt' => 'ASC'])]
    private Collection $payments;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastReminderAt = null;

    private function __construct(User $user, Season $season, Money $amountDue)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->holderName = mb_substr($user->displayName()->value, 0, 80);
        $this->season = $season->label();
        $this->amountDue = $amountDue->cents;
        $this->payments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function open(User $user, Season $season, Money $amountDue): self
    {
        return new self($user, $season, $amountDue);
    }

    public function changeAmountDue(Money $amount): void
    {
        $wasPaid = MembershipStatus::Paid === $this->status();
        $this->amountDue = $amount->cents;
        $this->recordIfNewlyPaid($wasPaid);
    }

    public function recordPayment(Money $amount, PaymentMethod $method, \DateTimeImmutable $paidAt, ?string $reference, ?User $recordedBy): Payment
    {
        if ($amount->isZero()) {
            throw new \InvalidArgumentException('Le montant du paiement doit être positif.');
        }

        $wasPaid = MembershipStatus::Paid === $this->status();
        $payment = new Payment($this, $amount, $method, $paidAt, $reference, $recordedBy);
        $this->payments->add($payment);
        $this->recordIfNewlyPaid($wasPaid);

        return $payment;
    }

    public function cancelPayment(Payment $payment, string $reason): void
    {
        if (!$this->payments->contains($payment)) {
            throw new \InvalidArgumentException('Ce paiement n\'appartient pas à cette cotisation.');
        }

        $payment->cancel($reason);
    }

    public function paidAmount(): Money
    {
        $total = Money::zero();
        foreach ($this->payments as $payment) {
            if (!$payment->isCancelled()) {
                $total = $total->add($payment->amount());
            }
        }

        return $total;
    }

    public function remaining(): Money
    {
        return $this->amountDue()->remainingAfter($this->paidAmount());
    }

    public function status(): MembershipStatus
    {
        $paid = $this->paidAmount();

        return match (true) {
            $paid->greaterThanOrEqual($this->amountDue()) => MembershipStatus::Paid,
            !$paid->isZero() => MembershipStatus::Partial,
            default => MembershipStatus::Unpaid,
        };
    }

    public function isOverdue(\DateTimeImmutable $now): bool
    {
        return MembershipStatus::Paid !== $this->status() && $now > $this->season()->dueDate();
    }

    /** Relance au plus une fois par semaine. */
    public function shouldBeReminded(\DateTimeImmutable $now): bool
    {
        return null !== $this->user
            && $this->isOverdue($now)
            && (null === $this->lastReminderAt || $now >= $this->lastReminderAt->add(new \DateInterval(self::REMINDER_INTERVAL)));
    }

    public function markReminded(\DateTimeImmutable $now): void
    {
        $this->lastReminderAt = $now;
    }

    /** Compte supprimé : la ligne comptable reste (version unitaire de MembershipRepository::detachUser()). */
    public function detachUser(): void
    {
        $this->user = null;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function holderName(): string
    {
        return $this->user?->displayName()->value ?? $this->holderName;
    }

    public function season(): Season
    {
        return Season::fromLabel($this->season);
    }

    public function amountDue(): Money
    {
        return Money::cents($this->amountDue);
    }

    /** @return list<Payment> */
    public function payments(): array
    {
        return array_values($this->payments->toArray());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastReminderAt(): ?\DateTimeImmutable
    {
        return $this->lastReminderAt;
    }

    private function recordIfNewlyPaid(bool $wasPaid): void
    {
        if (!$wasPaid && MembershipStatus::Paid === $this->status()) {
            $this->recordThat(new MembershipPaid($this->id));
        }
    }
}
