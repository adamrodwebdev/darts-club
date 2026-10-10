<?php

namespace App\Membership;

use App\Entity\Membership;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Translation\InvalidValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Cas d'usage du trésorier. Les événements (MembershipPaid) partent après le flush. */
final class Treasury
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function currentSeason(): Season
    {
        return Season::containing($this->clock->now());
    }

    /**
     * Ouvre la saison : une cotisation par adhérent vérifié qui n'en a pas encore.
     *
     * @return int nombre de cotisations créées
     */
    public function openSeason(Season $season, Money $amountDue): int
    {
        $existing = [];
        foreach ($this->memberships->forSeason($season) as $membership) {
            if (null !== $user = $membership->getUser()) {
                $existing[$user->getId()->toRfc4122()] = true;
            }
        }

        $created = 0;
        foreach ($this->users->findBy(['verified' => true]) as $user) {
            if (!isset($existing[$user->getId()->toRfc4122()])) {
                $this->em->persist(Membership::open($user, $season, $amountDue));
                ++$created;
            }
        }
        $this->em->flush();

        return $created;
    }

    public function openFor(User $user, Season $season, Money $amountDue): Membership
    {
        if (null !== $this->memberships->findOneFor($user, $season)) {
            throw new InvalidValue('Une cotisation %season% existe déjà pour cet adhérent.', ['%season%' => (string) $season]);
        }

        $membership = Membership::open($user, $season, $amountDue);
        $this->em->persist($membership);
        $this->em->flush();

        return $membership;
    }

    public function changeAmountDue(Membership $membership, Money $amount): void
    {
        $membership->changeAmountDue($amount);
        $this->em->flush();
    }

    public function recordPayment(Membership $membership, Money $amount, PaymentMethod $method, \DateTimeImmutable $paidAt, ?string $reference, User $treasurer): Payment
    {
        if ($paidAt > $this->clock->now()) {
            throw new \DomainException('La date de paiement ne peut pas être dans le futur.');
        }

        $payment = $membership->recordPayment($amount, $method, $paidAt, $reference, $treasurer);
        $this->em->flush();

        return $payment;
    }

    public function cancelPayment(Payment $payment, string $reason): void
    {
        $payment->getMembership()->cancelPayment($payment, $reason);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function dashboard(Season $season): array
    {
        $byStatus = array_fill_keys(array_map(static fn (MembershipStatus $s): string => $s->value, MembershipStatus::cases()), 0);
        $byMethod = [];
        $expected = Money::zero();
        $collected = Money::zero();
        $now = $this->clock->now();
        $overdue = 0;

        foreach ($this->memberships->forSeason($season) as $membership) {
            ++$byStatus[$membership->status()->value];
            $expected = $expected->add($membership->amountDue());
            $collected = $collected->add($membership->paidAmount());
            $overdue += $membership->isOverdue($now) ? 1 : 0;

            foreach ($membership->payments() as $payment) {
                if (!$payment->isCancelled()) {
                    $key = $payment->method()->value;
                    $byMethod[$key] = ($byMethod[$key] ?? Money::zero())->add($payment->amount());
                }
            }
        }

        return [
            'season' => $season->label(),
            'dueDate' => $season->dueDate()->format('Y-m-d'),
            'members' => array_sum($byStatus),
            'byStatus' => $byStatus,
            'overdue' => $overdue,
            'expected' => $expected->toDecimal(),
            'collected' => $collected->toDecimal(),
            'remaining' => $expected->remainingAfter($collected)->toDecimal(),
            'byMethod' => array_map(static fn (Money $m): string => $m->toDecimal(), $byMethod),
        ];
    }
}
