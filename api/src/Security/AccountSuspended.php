<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/** Connexion refusée : compte suspendu par le président. */
final class AccountSuspended extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct('Compte suspendu. Contacte le bureau du club.');
    }
}
