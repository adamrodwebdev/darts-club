<?php

namespace App\Dto;

use App\EventAccounts\AccountsFlags;

/** Ce que la soirée compte dans les limites de l'année (buvette temporaire, manifestation exonérée). */
final class AccountsFlagsInput
{
    public function __construct(
        public readonly bool $temporaryBar = false,
        public readonly bool $taxExempt = false,
    ) {
    }

    public function toFlags(): AccountsFlags
    {
        return new AccountsFlags($this->temporaryBar, $this->taxExempt);
    }
}
