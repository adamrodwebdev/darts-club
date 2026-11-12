<?php

declare(strict_types=1);

namespace App\Text;

/**
 * Nettoie un texte venu de l'extérieur sans changer ce qu'il dit (règle du 2026-11-09) : fins de
 * ligne unifiées, caractères invisibles retirés (contrôles, inversions et isolats bidirectionnels,
 * espace de largeur nulle, BOM, caractères d'étiquette ; les liants U+200C et U+200D restent, les
 * emoji et plusieurs écritures en ont besoin), Unicode composé (NFC), espaces resserrés, bords
 * taillés. Idempotent. Il n'échappe pas le HTML : le texte est échappé là où il s'affiche.
 *
 * Un texte qui n'est pas de l'UTF-8 valide est rendu tel quel : la contrainte Length, posée sur
 * chaque champ texte, le refuse (et RequestFields aussi).
 */
final class PlainText
{
    private const INVISIBLE = '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{200B}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}\x{E0000}-\x{E007F}]/u';

    /** Espaces Unicode (insécable, fine insécable…) en plus des espaces ASCII */
    private const SPACES = '[\s\p{Z}]';

    /** Champ d'une ligne : chaque suite d'espaces, retours à la ligne compris, devient un espace */
    public static function line(string $text): string
    {
        $visible = self::visible($text);

        return null === $visible ? $text : self::trim((string) preg_replace('/'.self::SPACES.'+/u', ' ', $visible));
    }

    /** Champ de plusieurs lignes : les retours à la ligne et les tabulations restent */
    public static function lines(string $text): string
    {
        $visible = self::visible($text);

        return null === $visible ? $text : self::trim($visible);
    }

    private static function visible(string $text): ?string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return null;
        }
        $visible = preg_replace(self::INVISIBLE, '', str_replace(["\r\n", "\r"], "\n", $text));
        if (null === $visible) {
            return null;
        }
        // Sans l'extension intl, le polyfill de Symfony fournit la classe (sans type de retour)
        $composed = \Normalizer::normalize($visible, \Normalizer::FORM_C);

        return \is_string($composed) ? $composed : null;
    }

    private static function trim(string $text): string
    {
        return (string) preg_replace('/^'.self::SPACES.'+|'.self::SPACES.'+$/u', '', $text);
    }
}
