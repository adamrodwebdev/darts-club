<?php

namespace App\Preference;

/** Langues de l'interface, des emails et des notifications. */
enum Language: string
{
    case French = 'fr';
    case English = 'en';

    /** Langue d'une requête (déjà négociée parmi framework.enabled_locales), français à défaut. */
    public static function fromLocale(?string $locale): self
    {
        return self::tryFrom(substr((string) $locale, 0, 2)) ?? self::French;
    }
}
