<?php

namespace App\ClubEvent;

/** Fuseau du club pour l'affichage (stockage en UTC avec décalage). */
final class ClubTime
{
    public const TIMEZONE = 'Europe/Paris';

    /** Motifs ICU par langue : « samedi 17 octobre à 14:00 », « Saturday 17 October at 14:00 » */
    private const PATTERNS = [
        'fr' => ['fr_FR', "EEEE d MMMM 'à' HH:mm"],
        'en' => ['en_GB', "EEEE d MMMM 'at' HH:mm"],
    ];

    public static function format(\DateTimeImmutable $date, string $locale = 'fr'): string
    {
        [$intlLocale, $pattern] = self::PATTERNS[$locale] ?? self::PATTERNS['fr'];
        $formatter = new \IntlDateFormatter($intlLocale, \IntlDateFormatter::FULL, \IntlDateFormatter::SHORT, self::TIMEZONE, null, $pattern);

        return (string) $formatter->format($date);
    }

    /** « 16/10/2026 » (même format en français et en anglais britannique) */
    public static function formatDay(\DateTimeImmutable $date, string $locale = 'fr'): string
    {
        $formatter = new \IntlDateFormatter(self::PATTERNS[$locale][0] ?? 'fr_FR', \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE, self::TIMEZONE, null, 'dd/MM/yyyy');

        return (string) $formatter->format($date);
    }
}
