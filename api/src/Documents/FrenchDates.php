<?php

namespace App\Documents;

use App\ClubEvent\ClubTime;

/** Dates des documents, en français et à l'heure de Paris (courriers aux administrations françaises). */
final class FrenchDates
{
    /** « samedi 14 novembre 2026 » */
    public static function day(\DateTimeImmutable $date): string
    {
        return self::format($date, 'EEEE d MMMM y');
    }

    /** « 14 novembre 2026 » */
    public static function date(\DateTimeImmutable $date): string
    {
        return self::format($date, 'd MMMM y');
    }

    /** « 20 h », « 20 h 30 » (espaces insécables : l'heure ne se coupe jamais en fin de ligne) */
    public static function time(\DateTimeImmutable $date): string
    {
        $local = $date->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE));
        $minutes = (int) $local->format('i');
        $space = FrenchTypography::NBSP;

        return (int) $local->format('G').$space.'h'.(0 === $minutes ? '' : $space.$local->format('i'));
    }

    /** « 1re », « 2e » : rang d'une demande */
    public static function ordinal(int $rank): string
    {
        return 1 === $rank ? '1re' : $rank.'e';
    }

    private static function format(\DateTimeImmutable $date, string $pattern): string
    {
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, ClubTime::TIMEZONE, null, $pattern);

        return (string) $formatter->format($date);
    }
}
