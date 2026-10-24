<?php

namespace App\EventAccounts;

/** Ce que la personne connectée peut faire du bilan (affichage des boutons ; l'API reste juge) */
final readonly class AccountsPermissions
{
    public function __construct(
        public bool $edit,
        public bool $reopen,
    ) {
    }
}
