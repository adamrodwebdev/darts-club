<?php

declare(strict_types=1);

namespace App\EventAccounts;

/** Couleur d'un jeton, reconnue d'un coup d'œil à l'accueil (les sachets n'en ont pas). */
enum TokenColor: string
{
    case Green = 'vert';
    case Yellow = 'jaune';
    case Red = 'rouge';
    case Blue = 'bleu';
    case Purple = 'violet';
    case Orange = 'orange';
    case White = 'blanc';
    case Black = 'noir';
}
