<?php

namespace App\Dto;

use App\EventAccounts\CashSettings;
use App\EventAccounts\TokenKind;
use App\Membership\Money;
use Symfony\Component\Validator\Constraints as Assert;

/** Encaissement de la soirée : jetons [{ name, value « 0,50 », bag }] et fond de caisse. */
final class CashSettingsInput
{
    /** @param list<mixed> $tokens */
    public function __construct(
        public readonly bool $tokensEnabled = false,
        #[Assert\Count(max: CashSettings::TOKENS_MAX)]
        public readonly array $tokens = [],
        #[Assert\NotBlank]
        public readonly string $cashFloat = '0',
    ) {
    }

    public function toSettings(): CashSettings
    {
        return new CashSettings(
            $this->tokensEnabled,
            array_map(static function (mixed $token): TokenKind {
                $token = \is_array($token) ? $token : [];

                return new TokenKind(
                    \is_string($token['name'] ?? null) ? $token['name'] : '',
                    Money::fromDecimal(\is_string($token['value'] ?? null) ? $token['value'] : ''),
                    true === ($token['bag'] ?? false),
                );
            }, $this->tokens),
            Money::fromDecimal($this->cashFloat),
        );
    }
}
