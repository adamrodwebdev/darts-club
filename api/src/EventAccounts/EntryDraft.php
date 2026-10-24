<?php

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/** Dépense ou recette du bilan, hors buvette, saisie par le bureau. */
final readonly class EntryDraft
{
    public const LABEL_MAX = 80;

    public string $label;

    public function __construct(
        public EntryKind $kind,
        string $label,
        public EntryAmounts $amounts,
    ) {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > self::LABEL_MAX) {
            throw new InvalidValue('Le nom doit faire entre 1 et %max% caractères.', ['%max%' => self::LABEL_MAX]);
        }
        $this->label = $label;
    }
}
