<?php

namespace App\Messaging;

use App\Translation\InvalidValue;

/** Texte brut d'un message (jamais interprété comme HTML). */
final readonly class MessageBody implements \Stringable
{
    public const MAX = 5000;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $text): self
    {
        // Caractères de contrôle retirés, sauf retours à la ligne et tabulations
        $clean = trim((string) preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $text)));
        $length = mb_strlen($clean);

        if (0 === $length) {
            throw new \InvalidArgumentException('Le message est vide.');
        }
        if ($length > self::MAX) {
            throw new InvalidValue('Le message ne doit pas dépasser %max% caractères.', ['%max%' => self::MAX]);
        }

        return new self($clean);
    }

    public function preview(int $length = 140): string
    {
        return mb_strimwidth((string) preg_replace('/\s+/u', ' ', $this->value), 0, $length, '…');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
