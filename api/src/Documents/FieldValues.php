<?php

namespace App\Documents;

/**
 * Valeurs des champs d'un document : celles saisies par le secrétariat, sinon les valeurs
 * pré-remplies. Seules les clés connues sont gardées, chacune selon sa nature.
 */
final readonly class FieldValues
{
    public const TEXT_MAX = 2000;

    /** @param array<string, string|bool> $values */
    private function __construct(
        private array $values,
    ) {
    }

    /**
     * @param list<FieldDefinition> $definitions
     * @param array<mixed>          $input       valeurs reçues (ou enregistrées)
     */
    public static function from(array $definitions, array $input): self
    {
        $values = [];
        foreach ($definitions as $field) {
            $values[$field->key] = self::value($field, $input[$field->key] ?? null);
        }

        return new self($values);
    }

    public function text(string $key): string
    {
        $value = $this->values[$key] ?? '';

        return \is_string($value) ? $value : '';
    }

    public function flag(string $key): bool
    {
        return true === ($this->values[$key] ?? false);
    }

    /** @return array<string, string|bool> */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Valeurs qui diffèrent du pré-remplissage : seules celles-ci sont gardées, les autres suivent la
     * soirée (une date ou un lieu changés se reportent dans le document).
     *
     * @param list<FieldDefinition> $definitions
     *
     * @return array<string, string|bool>
     */
    public function edits(array $definitions): array
    {
        $edits = [];
        foreach ($definitions as $field) {
            $value = $this->values[$field->key] ?? $field->default;
            if ($value !== $field->default) {
                $edits[$field->key] = $value;
            }
        }

        return $edits;
    }

    private static function value(FieldDefinition $field, mixed $input): string|bool
    {
        return match ($field->kind) {
            FieldKind::Checkbox => \is_bool($input) ? $input : (bool) $field->default,
            FieldKind::Choice => \is_string($input) && \in_array($input, $field->choices, true) ? $input : $field->default,
            default => \is_string($input) ? mb_substr(trim($input), 0, self::TEXT_MAX) : $field->default,
        };
    }
}
