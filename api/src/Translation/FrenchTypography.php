<?php

namespace App\Translation;

/**
 * Espaces insécables de la typographie française : avant « : ; ! ? % », à l'intérieur des
 * guillemets et après « n° ». Aucune ligne (courrier, email, notification) ne commence ainsi par
 * un signe orphelin.
 */
final class FrenchTypography
{
    public const NBSP = "\u{00A0}";

    public static function apply(string $text): string
    {
        return (string) preg_replace(['/ (?=[:;!?»%])/u', '/(?<=«) /u', '/(?<=\b[nN]°) /u'], self::NBSP, $text);
    }

    /** Texte d'une langue (« fr », « fr_FR »…) : typographie française pour le français, inchangé sinon */
    public static function forLocale(string $text, string $locale): string
    {
        return str_starts_with(strtolower($locale), 'fr') ? self::apply($text) : $text;
    }
}
