<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Mot de passe choisi à l'inscription, redemandé pour confirmer l'adresse email. */
final class PasswordConfirmation
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        #[\SensitiveParameter]
        public readonly string $password = '',
    ) {
    }
}
