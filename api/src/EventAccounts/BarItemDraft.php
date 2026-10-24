<?php

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/** Article de la buvette saisi par le bureau (nom, quantités, prix). */
final readonly class BarItemDraft
{
    public const LABEL_MAX = 80;

    public string $label;

    public function __construct(
        string $label,
        public ItemQuantities $quantities,
        public ItemPrices $prices,
    ) {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > self::LABEL_MAX) {
            throw new InvalidValue('Le nom doit faire entre 1 et %max% caractères.', ['%max%' => self::LABEL_MAX]);
        }
        $this->label = $label;
    }
}
