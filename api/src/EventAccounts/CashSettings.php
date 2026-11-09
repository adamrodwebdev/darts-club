<?php

declare(strict_types=1);

namespace App\EventAccounts;

use App\Membership\Money;
use App\Translation\InvalidValue;

/**
 * Encaissement de la soirée : consommations payées en jetons achetés à l'accueil (en espèces ou
 * par carte), jetons et sachets vendus avec leur valeur, fond de caisse de l'accueil.
 */
final readonly class CashSettings
{
    public const TOKENS_MAX = 6;
    private const FLOAT_MAX = 1_000_000;

    /** @var list<TokenKind> */
    public array $tokens;

    /** @param list<TokenKind> $tokens */
    public function __construct(
        public bool $tokensEnabled,
        array $tokens,
        public Money $cashFloat,
    ) {
        if (\count($tokens) > self::TOKENS_MAX) {
            throw new InvalidValue('%max% jetons ou sachets au plus.', ['%max%' => self::TOKENS_MAX]);
        }
        $names = array_map(static fn (TokenKind $token): string => mb_strtolower($token->name), $tokens);
        if (\count(array_unique($names)) !== \count($names)) {
            throw new InvalidValue('Deux jetons portent le même nom.');
        }
        if ($cashFloat->cents > self::FLOAT_MAX) {
            throw new InvalidValue('Fond de caisse invalide : 10 000 € au plus.');
        }
        $this->tokens = $tokens;
    }

    /** Habitude du club : jeton vert à 1 €, jeton jaune à 0,50 €, sachet à 10 € */
    public static function clubDefaults(): self
    {
        return new self(true, [
            new TokenKind('Jeton vert', Money::cents(100), color: TokenColor::Green),
            new TokenKind('Jeton jaune', Money::cents(50), color: TokenColor::Yellow),
            new TokenKind('Sachet', Money::cents(1000), bag: true),
        ], Money::zero());
    }

    /** Le jeton ou le sachet de ce nom, s'il est vendu ce soir */
    public function token(string $name): ?TokenKind
    {
        foreach ($this->tokens as $token) {
            if (mb_strtolower($token->name) === mb_strtolower(trim($name))) {
                return $token;
            }
        }

        return null;
    }

    /** Mêmes jetons vendus (noms, valeurs, sachets), dans le même mode : ce qui se fige à la première vente */
    public function sellsTheSameAs(self $other): bool
    {
        if ($this->tokensEnabled !== $other->tokensEnabled || \count($this->tokens) !== \count($other->tokens)) {
            return false;
        }
        foreach ($this->tokens as $token) {
            $same = $other->token($token->name);
            if (null === $same || !$token->sameAs($same)) {
                return false;
            }
        }

        return true;
    }
}
