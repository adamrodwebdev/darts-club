<?php

namespace App\Dto;

use App\Membership\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;

final class PaymentInput
{
    public function __construct(
        /** Montant décimal : « 30 », « 30.50 », « 30,50 » */
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d{1,6}([.,]\d{1,2})?$/', message: 'Montant invalide.')]
        public readonly string $amount = '',

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'methods'])]
        public readonly string $method = '',

        #[Assert\NotBlank]
        #[Assert\Date]
        public readonly string $paidAt = '',

        #[Assert\Length(max: 80)]
        public readonly ?string $reference = null,
    ) {
    }

    /** @return list<string> */
    public static function methods(): array
    {
        return array_map(static fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases());
    }
}
