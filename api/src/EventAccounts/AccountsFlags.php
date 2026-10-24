<?php

namespace App\EventAccounts;

/**
 * Ce que la soirée compte dans les limites de l'année : une buvette temporaire autorisée par la
 * mairie (5 par an, Code de la santé publique) et une manifestation de soutien exonérée d'impôts
 * (6 par an). Deux compteurs distincts.
 */
final readonly class AccountsFlags
{
    public const TEMPORARY_BARS_PER_YEAR = 5;
    public const TAX_EXEMPT_EVENTS_PER_YEAR = 6;

    public function __construct(
        public bool $temporaryBar = false,
        public bool $taxExempt = false,
    ) {
    }
}
