<?php

namespace App\Security\Passkey;

/**
 * Clé d'accès refusée (défi, origine, signature, compteur…). Le détail reste dans les journaux ;
 * l'adhérent ne voit qu'un message général.
 */
final class InvalidPasskey extends \RuntimeException
{
}
