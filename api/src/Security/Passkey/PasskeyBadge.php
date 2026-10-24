<?php

namespace App\Security\Passkey;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/** Connexion faite avec une clé d'accès : la deuxième étape par clé serait un doublon. */
final class PasskeyBadge implements BadgeInterface
{
    public function isResolved(): bool
    {
        return true;
    }
}
