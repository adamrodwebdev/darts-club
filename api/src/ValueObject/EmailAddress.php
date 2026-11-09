<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Translation\InvalidValue;

/** Adresse email normalisée (minuscules, sans espaces). */
final readonly class EmailAddress implements \Stringable
{
    /** Longueur maximale d'une adresse, en caractères */
    public const MAX = 180;

    /** @param non-empty-string $value */
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $email): self
    {
        $normalized = mb_strtolower(trim($email));

        if ('' === $normalized || false === filter_var($normalized, \FILTER_VALIDATE_EMAIL) || mb_strlen($normalized) > self::MAX) {
            throw new InvalidValue('Adresse email invalide : « %email% ».', ['%email%' => $email]);
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
