<?php

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/**
 * Comment un article est servi : par le bar ou la cuisine, compté au litre, à l'unité ou à la
 * portion, avec ses garnitures (ketchup, mayonnaise…) et, pour une boisson, s'il contient de
 * l'alcool. Une buvette temporaire ne vend que les groupes 1 (sans alcool) et 3 (bière, vin, cidre).
 */
final readonly class ItemServing
{
    public const GARNISHES_MAX = 8;
    public const GARNISH_MAX = 40;

    /** @var list<string> */
    public array $garnishes;

    /** @param list<string> $garnishes */
    public function __construct(
        public Station $station,
        public StockUnit $unit,
        array $garnishes = [],
        public bool $alcoholic = false,
    ) {
        $kept = [];
        foreach ($garnishes as $garnish) {
            $garnish = trim($garnish);
            if ('' === $garnish || mb_strlen($garnish) > self::GARNISH_MAX) {
                throw new InvalidValue('Une garniture fait entre 1 et %max% caractères.', ['%max%' => self::GARNISH_MAX]);
            }
            // Une seule fois chacune, écrite comme la première saisie
            $kept[mb_strtolower($garnish)] ??= $garnish;
        }
        if (\count($kept) > self::GARNISHES_MAX) {
            throw new InvalidValue('%max% garnitures au plus par article.', ['%max%' => self::GARNISHES_MAX]);
        }
        if ($alcoholic && Station::Bar !== $station) {
            throw new InvalidValue('Une boisson alcoolisée se sert au bar.');
        }
        $this->garnishes = array_values($kept);
    }
}
