<?php

namespace App\ValueObject;

use App\Translation\InvalidValue;

/** Pseudo affiché aux autres adhérents (messagerie, événements). */
final readonly class DisplayName implements \Stringable
{
    public const MIN = 2;
    public const MAX = 40;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $name): self
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $name));
        $length = mb_strlen($normalized);

        if ($length < self::MIN || $length > self::MAX) {
            throw new InvalidValue('Le pseudo doit faire entre %min% et %max% caractères.', ['%min%' => self::MIN, '%max%' => self::MAX]);
        }
        if (1 !== preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} ._'-]*$/u", $normalized)) {
            throw new \InvalidArgumentException('Le pseudo contient des caractères non autorisés.');
        }

        return new self($normalized);
    }

    /** Pseudo par défaut tiré de l'email (« jean.dupont@… » → « jean.dupont »). */
    public static function fromEmail(EmailAddress $email): self
    {
        $local = strstr($email->value, '@', true);

        try {
            return self::fromString(mb_substr(false === $local ? '' : $local, 0, self::MAX));
        } catch (\InvalidArgumentException) {
            return new self('Adhérent');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
