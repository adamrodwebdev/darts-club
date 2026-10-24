<?php

namespace App\EventAccounts;

use App\Membership\Money;
use App\Translation\InvalidValue;

/** Ce qu'indique le bureau pour un justificatif : son nom, son montant, et s'il s'agit d'un avoir. */
final readonly class ReceiptDetails
{
    public const LABEL_MAX = 80;

    public string $label;

    public function __construct(
        string $label,
        public ?Money $amount = null,
        /** Avoir (retour fournisseur remboursé) : montant à déduire */
        public bool $credit = false,
    ) {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > self::LABEL_MAX) {
            throw new InvalidValue('Le nom doit faire entre 1 et %max% caractères.', ['%max%' => self::LABEL_MAX]);
        }
        $this->label = $label;
    }
}
