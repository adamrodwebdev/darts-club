<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Nouveau mot de passe (lien « mot de passe oublié ») : mêmes règles qu'à l'inscription. */
final class NewPassword
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 12, max: 4096)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        #[Assert\NotCompromisedPassword]
        #[\SensitiveParameter]
        public readonly string $password = '',
    ) {
    }
}
