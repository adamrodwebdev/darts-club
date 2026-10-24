<?php

namespace App\Dto;

use App\Validator\PasswordPolicy;

/** Nouveau mot de passe (lien « mot de passe oublié ») : mêmes règles qu'à l'inscription. */
final class NewPassword
{
    public function __construct(
        #[PasswordPolicy]
        #[\SensitiveParameter]
        public readonly string $password = '',
    ) {
    }
}
