<?php

namespace App\ValueObject;

/** Adresse email normalisée (minuscules, sans espaces). */
final readonly class EmailAddress implements \Stringable
{
    /** @param non-empty-string $value */
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $email): self
    {
        $normalized = mb_strtolower(trim($email));

        if ('' === $normalized || false === filter_var($normalized, \FILTER_VALIDATE_EMAIL) || mb_strlen($normalized) > 180) {
            throw new \InvalidArgumentException(\sprintf('Adresse email invalide : "%s".', $email));
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
