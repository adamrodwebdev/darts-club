<?php

declare(strict_types=1);

namespace App\Controller;

use App\Text\TextMode;
use App\Translation\Unprocessable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Champs d'un corps de requête lu sans DTO (règle du 2026-11-09 : la donnée est vérifiée à la
 * porte). Un texte doit être une chaîne : il est nettoyé (PlainText), puis refusé au-delà de sa
 * longueur maximale, jamais coupé. Un secret (code, jeton) est seulement borné. Un champ absent
 * ou null vaut une chaîne vide : le domaine décide s'il est obligatoire.
 */
final readonly class RequestFields
{
    /** @param array<mixed> $fields */
    private function __construct(
        private array $fields,
    ) {
    }

    /** Corps JSON de la requête ; un corps vide n'a aucun champ */
    public static function of(Request $request): self
    {
        return new self('' === $request->getContent() ? [] : $request->toArray());
    }

    /** Champs texte d'un formulaire multipart (un justificatif et son libellé) */
    public static function ofForm(Request $request): self
    {
        return new self($request->request->all());
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->fields);
    }

    /** Texte d'une ligne, nettoyé : chaque suite d'espaces devient un espace */
    public function line(string $key, int $max): string
    {
        return $this->text($key, $max, TextMode::Line);
    }

    /** Texte de plusieurs lignes, nettoyé : les retours à la ligne restent */
    public function lines(string $key, int $max): string
    {
        return $this->text($key, $max, TextMode::Lines);
    }

    /** Code, jeton ou mot de passe : jamais nettoyé, seulement borné */
    public function secret(string $key, int $max): string
    {
        return $this->text($key, $max, TextMode::Secret);
    }

    private function text(string $key, int $max, TextMode $mode): string
    {
        $value = $this->fields[$key] ?? null;
        if (null === $value) {
            return '';
        }
        if (!\is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new Unprocessable('Le champ « %field% » doit être un texte.', parameters: ['%field%' => $key]);
        }
        $text = $mode->clean($value);
        if (mb_strlen($text) > $max) {
            throw new Unprocessable('Le champ « %field% » dépasse %max% caractères.', parameters: ['%field%' => $key, '%max%' => $max]);
        }

        return $text;
    }
}
