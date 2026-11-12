<?php

declare(strict_types=1);

namespace App\EventAccounts;

use App\Membership\Money;
use App\Translation\InvalidValue;

/** Jeton (vert à 1 €, jaune à 0,50 €) ou sachet de jetons (10 €) vendu à l'accueil. */
final readonly class TokenKind
{
    public const NAME_MAX = 30;
    private const VALUE_MAX = 100_000;

    public string $name;

    /** Couleur du jeton ; aucune pour un sachet */
    public ?TokenColor $color;

    public function __construct(
        string $name,
        public Money $value,
        public bool $bag = false,
        ?TokenColor $color = null,
    ) {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::NAME_MAX) {
            throw new InvalidValue('Le nom d’un jeton fait entre 1 et %max% caractères.', ['%max%' => self::NAME_MAX]);
        }
        if ($value->cents < 1 || $value->cents > self::VALUE_MAX) {
            throw new InvalidValue('Un jeton ou un sachet vaut entre 0,01 € et 1 000 €.');
        }
        $this->name = $name;
        $this->color = $bag ? null : $color;
    }

    /** @return array{name: string, value: int, bag: bool, color: string|null} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'value' => $this->value->cents, 'bag' => $this->bag, 'color' => $this->color?->value];
    }

    /** @param array<mixed> $stored */
    public static function fromArray(array $stored): self
    {
        $color = $stored['color'] ?? null;

        return new self(
            \is_string($stored['name'] ?? null) ? $stored['name'] : '?',
            Money::cents(\is_int($stored['value'] ?? null) ? $stored['value'] : 1),
            true === ($stored['bag'] ?? false),
            \is_string($color) ? TokenColor::tryFrom($color) : null,
        );
    }

    /** Même jeton pour la caisse : nom, valeur et nature (la couleur n'est qu'un repère) */
    public function sameAs(self $other): bool
    {
        return mb_strtolower($this->name) === mb_strtolower($other->name) && $this->value->cents === $other->value->cents && $this->bag === $other->bag;
    }
}
