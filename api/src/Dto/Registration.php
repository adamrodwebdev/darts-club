<?php

declare(strict_types=1);

namespace App\Dto;

use App\Text\Secret;
use App\Validator\PasswordPolicy;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

final class Registration
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',

        #[PasswordPolicy]
        #[\SensitiveParameter]
        #[Secret]
        public readonly string $password = '',

        #[PhoneNumber(defaultRegion: 'FR')]
        #[Assert\Length(max: 30)]
        public readonly ?string $phone = null,
    ) {
    }
}
