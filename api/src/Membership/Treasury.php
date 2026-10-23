<?php

namespace App\Membership;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\Membership;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Translation\InvalidValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Cas d'usage du trésorier. Les événements (MembershipPaid) partent après le flush ; chaque
 * opération qui touche à l'argent ou aux données des adhérents est inscrite au journal.
 */
final class Treasury
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly AuditTrail $audit,
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
    public function openSeason(SeasonFee $fee, User $treasurer): int
    {
        $season = $fee->season;
        $amountDue = $fee->amount;
        $existing = [];
        foreach ($this->memberships->forSeason($season) as $membership) {
            if (null !== $user = $membership->getUser()) {
                $existing[$user->getId()->toRfc4122()] = true;
            }
        }

        $created = 0;
        foreach ($this->users->findBy(['verified' => true]) as $user) {
            if (!isset($existing[$user->getId()->toRfc4122()])) {
                $this->em->persist(Membership::open($user, new SeasonFee($season, $amountDue), $this->clock->now()));
                ++$created;
            }
        }
        $this->audit->record($treasurer, new AuditEntry('season.open', $season->label(), ['amount' => $amountDue->toDecimal(), 'created' => $created]));
        $this->em->flush();

        return $created;
    }

    public function openFor(User $user, SeasonFee $fee, User $treasurer): Membership
    {
        if (null !== $this->memberships->findOneFor($user, $fee->season)) {
            throw new InvalidValue('Une cotisation %season% existe déjà pour cet adhérent.', ['%season%' => (string) $fee->season]);
        }

        $membership = Membership::open($user, $fee, $this->clock->now());
        $this->em->persist($membership);
        $this->audit->record($treasurer, new AuditEntry('membership.open', $membership->holderName(), ['season' => $fee->season->label(), 'amount' => $fee->amount->toDecimal()]));
        $this->em->flush();

        return $membership;
    }

    public function changeAmountDue(Membership $membership, Money $amount, User $treasurer): void
    {
        $before = $membership->amountDue()->toDecimal();
        $membership->changeAmountDue($amount);
        $this->audit->record($treasurer, new AuditEntry('membership.amount', $membership->holderName(), [
            'season' => $membership->season()->label(),
            'before' => $before,
            'after' => $amount->toDecimal(),
        ]));
        $this->em->flush();
    }

    public function recordPayment(Membership $membership, PaymentDetails $details, User $treasurer): void
    {
        $now = $this->clock->now();
        if ($details->paidAt > $now) {
            throw new \DomainException('La date de paiement ne peut pas être dans le futur.');
        }

        $membership->recordPayment($details, $treasurer, $now);
        $this->audit->record($treasurer, new AuditEntry('payment.record', $membership->holderName(), [
            'season' => $membership->season()->label(),
            'amount' => $details->amount->toDecimal(),
            'method' => $details->method->value,
        ]));
        $this->em->flush();
    }

    public function cancelPayment(Payment $payment, string $reason, User $treasurer): void
    {
        $membership = $payment->getMembership();
        $membership->cancelPayment($payment, $reason, $this->clock->now());
        $this->audit->record($treasurer, new AuditEntry('payment.cancel', $membership->holderName(), [
            'season' => $membership->season()->label(),
            'amount' => $payment->amount()->toDecimal(),
            'reason' => (string) $payment->cancelReason(),
        ]));
        $this->em->flush();
    }

    /** Export des cotisations (données personnelles) : qui l'a téléchargé, et quand */
    public function recordExport(Season $season, User $treasurer): void
    {
        $this->audit->record($treasurer, new AuditEntry('members.export', $season->label()));
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
