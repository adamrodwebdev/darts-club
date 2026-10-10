<?php

namespace App\ClubEvent;

/** Fuseau du club pour l'affichage (stockage en UTC avec décalage). */
final class ClubTime
{
    public const TIMEZONE = 'Europe/Paris';

    /** « samedi 17 octobre à 14:00 » */
    public static function format(\DateTimeImmutable $date): string
    {
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::SHORT, self::TIMEZONE, null, "EEEE d MMMM 'à' HH:mm");

        return (string) $formatter->format($date);
    }
}
