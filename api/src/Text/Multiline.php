<?php

declare(strict_types=1);

namespace App\Text;

/**
 * Champ de DTO écrit sur plusieurs lignes (un message, une description) : le nettoyage garde ses
 * retours à la ligne. Sans cet attribut, un texte est nettoyé comme une ligne.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Multiline
{
}
