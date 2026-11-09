<?php

declare(strict_types=1);

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/**
 * Article de la carte saisi par le bureau : son nom, comment il est servi, ce qui a été acheté et
 * les formats vendus. Les ventes ne dépassent jamais le stock quand les contenances sont connues.
 */
final readonly class BarItemDraft
{
    public const LABEL_MAX = 80;

    public string $label;

    public function __construct(
        string $label,
        public ItemServing $serving,
        public ItemStock $stock,
        public SaleFormats $formats,
    ) {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > self::LABEL_MAX) {
            throw new InvalidValue('Le nom doit faire entre 1 et %max% caractères.', ['%max%' => self::LABEL_MAX]);
        }
        $served = $formats->served();
        if (null !== $served && $served > $stock->units()) {
            throw new InvalidValue('Les ventes dépassent ce qui a été acheté, moins ce qui a été rendu.');
        }
        $this->label = $label;
    }

    /** Même article, mais les ventes déjà comptées par les commandes de la soirée */
    public function keepingSoldOf(SaleFormats $current): self
    {
        return new self(label: $this->label, serving: $this->serving, stock: $this->stock, formats: $this->formats->keepingSoldOf($current));
    }
}
