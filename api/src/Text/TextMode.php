<?php

declare(strict_types=1);

namespace App\Text;

/** Comment nettoyer un texte reçu : une ligne, plusieurs lignes, ou pas du tout (un secret) */
enum TextMode
{
    case Line;
    case Lines;
    case Secret;

    public function clean(string $text): string
    {
        return match ($this) {
            self::Line => PlainText::line($text),
            self::Lines => PlainText::lines($text),
            self::Secret => $text,
        };
    }
}
