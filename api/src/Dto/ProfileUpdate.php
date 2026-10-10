<?php

namespace App\Dto;

use App\ValueObject\DisplayName;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

/** Champs absents = inchangés ; phone vide = suppression du numéro. */
final class ProfileUpdate
{
    public function __construct(
        #[Assert\Length(min: DisplayName::MIN, max: DisplayName::MAX)]
        public readonly ?string $displayName = null,

        #[PhoneNumber(defaultRegion: 'FR')]
        public readonly ?string $phone = null,
    ) {
    }
}
