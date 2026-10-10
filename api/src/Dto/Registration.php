<?php

namespace App\Dto;

use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

final class Registration
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 12, max: 4096)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        #[Assert\NotCompromisedPassword]
        #[\SensitiveParameter]
        public readonly string $password = '',

        #[PhoneNumber(defaultRegion: 'FR')]
        public readonly ?string $phone = null,
    ) {
    }
}
