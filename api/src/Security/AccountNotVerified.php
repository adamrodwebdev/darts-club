<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/** Connexion refusée tant que l'adresse email n'est pas confirmée. */
final class AccountNotVerified extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct('Adresse email non vérifiée.');
    }
}
