<?php

namespace App\Documents;

/**
 * Champ d'un document : sa clé (libellé traduit par l'écran), sa nature et sa valeur pré-remplie
 * (tirée de la soirée ou de la fiche de l'association).
 */
final readonly class FieldDefinition
{
    /** @param list<string> $choices identifiants proposés (nature « choice ») */
    public function __construct(
        public string $key,
        public FieldKind $kind,
        public string|bool $default = '',
        public array $choices = [],
    ) {
    }

    public static function text(string $key, string $default): self
    {
        return new self($key, FieldKind::Text, $default);
    }

    public static function textarea(string $key, string $default): self
    {
        return new self($key, FieldKind::Textarea, $default);
    }

    public static function checkbox(string $key, bool $default): self
    {
        return new self($key, FieldKind::Checkbox, $default);
    }

    /** @param list<string> $choices */
    public static function choice(string $key, array $choices, string $default): self
    {
        return new self($key, FieldKind::Choice, $default, $choices);
    }

    /** @return array<string, mixed> */
    public function toArray(string|bool $value): array
    {
        return ['key' => $this->key, 'kind' => $this->kind->value, 'value' => $value, 'choices' => $this->choices];
    }
}
