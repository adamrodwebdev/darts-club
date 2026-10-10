<?php

namespace App\Membership;

use App\Entity\Membership;
use App\Entity\Payment;

/** Représentation JSON des cotisations (vue trésorier, email inclus ; vue adhérent sans). */
final class TreasuryView
{
    /** @return array<string, mixed> */
    public static function membership(Membership $membership, bool $forTreasurer, \DateTimeImmutable $now): array
    {
        $user = $membership->getUser();

        $data = [
            'id' => $membership->getId()->toRfc4122(),
            'season' => $membership->season()->label(),
            'holder' => [
                'id' => $user?->getId()->toRfc4122(),
                'displayName' => $membership->holderName(),
            ],
            'amountDue' => $membership->amountDue()->toDecimal(),
            'paid' => $membership->paidAmount()->toDecimal(),
            'remaining' => $membership->remaining()->toDecimal(),
            'status' => ['id' => $membership->status()->value, 'label' => $membership->status()->label()],
            'overdue' => $membership->isOverdue($now),
            'dueDate' => $membership->season()->dueDate()->format('Y-m-d'),
            'payments' => array_map(static fn (Payment $p): array => self::payment($p, $forTreasurer), $membership->payments()),
        ];

        if ($forTreasurer) {
            $data['holder']['email'] = $user?->getEmail();
            $data['lastReminderAt'] = $membership->getLastReminderAt()?->format(\DATE_ATOM);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function payment(Payment $payment, bool $forTreasurer): array
    {
        $data = [
            'id' => $payment->getId()->toRfc4122(),
            'amount' => $payment->amount()->toDecimal(),
            'method' => ['id' => $payment->method()->value, 'label' => $payment->method()->label()],
            'paidAt' => $payment->paidAt()->format('Y-m-d'),
            'cancelled' => $payment->isCancelled(),
        ];

        if ($forTreasurer) {
            $data += [
                'reference' => $payment->reference(),
                'recordedBy' => $payment->recordedBy()?->displayName()->value,
                'recordedAt' => $payment->recordedAt()->format(\DATE_ATOM),
                'cancelReason' => $payment->cancelReason(),
            ];
        }

        return $data;
    }
}
