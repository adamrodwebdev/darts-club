<?php

declare(strict_types=1);

namespace App\Text;

/**
 * Champ de DTO secret (mot de passe, code, jeton) : jamais nettoyé, car le tailler ou le
 * normaliser le changerait. Sa longueur reste bornée par sa contrainte Length.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Secret
{
}
