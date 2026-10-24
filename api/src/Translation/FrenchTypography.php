<?php

namespace App\Documents;

/**
 * Espaces insécables de la typographie française : avant « : ; ! ? », à l'intérieur des guillemets
 * et après « n° ». Aucune ligne d'un courrier ne commence ainsi par un signe orphelin.
 */
final class FrenchTypography
{
    public const NBSP = "\u{00A0}";

    public static function apply(string $text): string
    {
        return (string) preg_replace(['/ (?=[:;!?»])/u', '/(?<=«) /u', '/(?<=\b[nN]°) /u'], self::NBSP, $text);
    }
}
