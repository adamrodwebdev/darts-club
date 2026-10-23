<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\InputBag;

/** Paramètres d'un lien « mot de passe oublié » (PasswordResetLinks), tels que la page les renvoie. */
final readonly class ResetLink
{
    private function __construct(
        public string $userId,
        public int $expires,
        public string $token,
    ) {
    }

    /** @param InputBag<string> $query */
    public static function fromQuery(InputBag $query): self
    {
        return new self(
            mb_substr($query->getString('id'), 0, 36),
            $query->getInt('expires'),
            mb_substr($query->getString('token'), 0, 64),
        );
    }
}
