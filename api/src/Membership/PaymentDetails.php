<?php

namespace App\Membership;

/** Ce que le trésorier saisit pour un paiement (montant, moyen, date, référence) */
final readonly class PaymentDetails
{
    public ?string $reference;

    public function __construct(
        public Money $amount,
        public PaymentMethod $method,
        public \DateTimeImmutable $paidAt,
        ?string $reference = null,
    ) {
        if ($amount->isZero()) {
            throw new \InvalidArgumentException('Le montant du paiement doit être positif.');
        }
        $reference = null !== $reference ? trim($reference) : '';
        $this->reference = '' !== $reference ? mb_substr($reference, 0, 80) : null;
    }
}
