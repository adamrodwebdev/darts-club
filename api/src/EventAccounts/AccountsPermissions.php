<?php

declare(strict_types=1);

namespace App\EventAccounts;

/** Ce que la personne connectée peut faire du bilan (affichage des boutons ; l'API reste juge) */
final readonly class AccountsPermissions
{
    public function __construct(
        public bool $edit,
        public bool $reopen,
        /** Carte, encaissement et équipes : tout le bureau et l'administrateur */
        public bool $editMenu = false,
    ) {
    }
}
