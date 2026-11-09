<?php

declare(strict_types=1);

namespace App\EventAccounts;

/**
 * Ce que la cuisine (ou le bar) a déclaré épuisé : l'article entier, ou certaines de ses
 * garnitures. Un article épuisé disparaît de la prise de commande.
 */
final readonly class ItemAvailability
{
    /** @var list<string> */
    public array $garnishesOut;

    /** @param list<string> $garnishesOut */
    public function __construct(
        public bool $soldOut = false,
        array $garnishesOut = [],
    ) {
        $this->garnishesOut = array_values(array_unique(array_map('trim', $garnishesOut)));
    }

    public function lacks(string $garnish): bool
    {
        foreach ($this->garnishesOut as $out) {
            if (mb_strtolower($out) === mb_strtolower(trim($garnish))) {
                return true;
            }
        }

        return false;
    }
}
