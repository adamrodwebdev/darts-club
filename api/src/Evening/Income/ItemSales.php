<?php

namespace App\Evening\Income;

use App\Evening\OrderedItem;
use App\Evening\OrderLine;
use App\Membership\Money;

/**
 * Ventes d'un article ce soir, par format, tirées des lignes de commande : l'article tel que la carte
 * le proposait à sa première commande, et ce qui en est parti en unités de stock.
 */
final readonly class ItemSales
{
    /** @param list<FormatSales> $formats */
    private function __construct(
        public OrderedItem $item,
        public array $formats,
        /** Servi en unités de stock (centilitres, unités, portions) ; null si une contenance manque */
        public ?int $served,
    ) {
    }

    public static function of(OrderLine $line): self
    {
        return new self($line->item, [FormatSales::of($line)], self::units($line));
    }

    public function with(OrderLine $line): self
    {
        $format = $line->item->format;
        $known = [] !== array_filter($this->formats, static fn (FormatSales $sales): bool => $sales->isFormat($format));
        $formats = $known
            ? array_map(static fn (FormatSales $sales): FormatSales => $sales->isFormat($format) ? $sales->plus($line) : $sales, $this->formats)
            : [...$this->formats, FormatSales::of($line)];
        $units = self::units($line);

        return new self($this->item, $formats, null === $this->served || null === $units ? null : $this->served + $units);
    }

    public function amount(): Money
    {
        return array_reduce($this->formats, static fn (Money $sum, FormatSales $sales): Money => $sum->add($sales->amount), Money::zero());
    }

    public function count(): int
    {
        return array_sum(array_map(static fn (FormatSales $sales): int => $sales->count, $this->formats));
    }

    /** Ventes d'un format, s'il en est sorti */
    public function ofFormat(string $name): ?FormatSales
    {
        foreach ($this->formats as $sales) {
            if ($sales->isFormat($name)) {
                return $sales;
            }
        }

        return null;
    }

    private static function units(OrderLine $line): ?int
    {
        return null === $line->item->volume ? null : $line->item->volume * $line->count;
    }
}
