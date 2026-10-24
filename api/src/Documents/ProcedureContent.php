<?php

namespace App\Documents;

use App\Translation\InvalidValue;

/**
 * Ce que le secrétariat a changé dans un document : les champs modifiés (le reste suit la soirée) et
 * les morceaux de texte réécrits, par identifiant (texte vide : morceau retiré).
 */
final readonly class ProcedureContent
{
    public const REWRITES_MAX = 60;
    public const REWRITE_MAX = 4000;

    /**
     * @param array<string, string|bool> $values
     * @param array<string, string>      $rewrites
     */
    public function __construct(
        public array $values = [],
        public array $rewrites = [],
    ) {
        if (\count($rewrites) > self::REWRITES_MAX) {
            throw new InvalidValue('Trop de passages réécrits (%max% au plus).', ['%max%' => self::REWRITES_MAX]);
        }
        foreach ($rewrites as $text) {
            if (mb_strlen($text) > self::REWRITE_MAX) {
                throw new InvalidValue('Un passage réécrit dépasse %max% caractères.', ['%max%' => self::REWRITE_MAX]);
            }
        }
    }

    /**
     * Contenu reçu de l'écran : valeurs et réécritures de forme correcte seulement.
     *
     * @param array<mixed> $values
     * @param array<mixed> $rewrites
     */
    public static function fromInput(array $values, array $rewrites): self
    {
        $fields = [];
        foreach ($values as $key => $value) {
            if (\is_string($key) && (\is_string($value) || \is_bool($value))) {
                $fields[$key] = $value;
            }
        }
        // Un passage réécrit vide est retiré du document
        $texts = [];
        foreach ($rewrites as $id => $text) {
            if (\is_string($id) && \is_string($text) && 1 === preg_match('/^[a-z0-9-]{1,80}$/', $id)) {
                $texts[$id] = trim($text);
            }
        }

        return new self($fields, $texts);
    }
}
