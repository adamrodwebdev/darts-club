<?php

declare(strict_types=1);

namespace App\Dto;

use App\Text\Secret;
use App\Validator\PasswordPolicy;

/** Nouveau mot de passe (lien « mot de passe oublié ») : mêmes règles qu'à l'inscription. */
final class NewPassword
{
    public function __construct(
        #[PasswordPolicy]
        #[\SensitiveParameter]
        #[Secret]
        public readonly string $password = '',
    ) {
    }
}
