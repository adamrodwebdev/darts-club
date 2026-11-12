<?php

declare(strict_types=1);

namespace App\Evening;

/** Moyen de paiement à l'accueil, seul endroit où l'argent change de mains. */
enum PaymentMethod: string
{
    case Cash = 'especes';
    case Card = 'carte';
}
