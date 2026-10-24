<?php

namespace App\EventAccounts;

/** Ligne du bilan hors buvette : dépense (salle, SACEM…) ou recette (participation, dons…). */
enum EntryKind: string
{
    case Expense = 'depense';
    case Income = 'recette';
}
