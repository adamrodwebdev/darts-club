<?php

namespace App\Evening;

use App\EventAccounts\TokenKind;
use App\Membership\Money;
use App\Translation\InvalidValue;

/**
 * Ligne d'une vente de l'accueil : un jeton ou un sachet, sa valeur figée au moment de la vente
 * (changer les réglages ensuite ne touche pas les ventes passées), et la quantité.
 */
final readonly class SaleLine
{
    public const COUNT_MAX = 1000;

    public function __construct(
        public TokenKind $token,
        public int $count,
    ) {
        if ($count < 1 || $count > self::COUNT_MAX) {
            throw new InvalidValue('Quantité invalide : de 1 à %max%.', ['%max%' => self::COUNT_MAX]);
        }
    }

    public function amount(): Money
    {
        return $this->token->value->times($this->count);
    }

    /** @return array{token: array{name: string, value: int, bag: bool, color: string|null}, count: int} */
    public function toArray(): array
    {
        return ['token' => $this->token->toArray(), 'count' => $this->count];
    }

    /** @param array<mixed> $stored */
    public static function fromArray(array $stored): self
    {
        $count = $stored['count'] ?? 1;

        return new self(TokenKind::fromArray(\is_array($stored['token'] ?? null) ? $stored['token'] : []), \is_int($count) ? $count : 1);
    }
}
