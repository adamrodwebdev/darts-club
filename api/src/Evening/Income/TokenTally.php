<?php

namespace App\Evening\Income;

use App\EventAccounts\TokenKind;
use App\Membership\Money;

/** Jetons ou sachets d'une sorte vendus ce soir à l'accueil, à leur valeur figée dans chaque vente. */
final readonly class TokenTally
{
    public function __construct(
        public TokenKind $token,
        public int $count,
    ) {
    }

    public function amount(): Money
    {
        return $this->token->value->times($this->count);
    }

    public function plus(int $count): self
    {
        return new self($this->token, $this->count + $count);
    }
}
