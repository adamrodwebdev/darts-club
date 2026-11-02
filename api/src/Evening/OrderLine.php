<?php

namespace App\Evening;

use App\EventAccounts\Station;
use App\Membership\Money;
use App\Translation\InvalidValue;

/**
 * Ligne d'une commande, figée au moment de la prise : article, format, prix et contenance du
 * moment (changer la carte ensuite ne touche pas les commandes passées), quantité, garnitures.
 */
final readonly class OrderLine
{
    public const COUNT_MAX = 50;

    /** @param list<string> $garnishes */
    public function __construct(
        public OrderedItem $item,
        public int $count,
        public array $garnishes = [],
    ) {
        if ($count < 1 || $count > self::COUNT_MAX) {
            throw new InvalidValue('Quantité invalide : de 1 à %max%.', ['%max%' => self::COUNT_MAX]);
        }
    }

    public function amount(): Money
    {
        return $this->item->price->times($this->count);
    }

    public function forKitchen(): bool
    {
        return Station::Kitchen === $this->item->station;
    }

    /** @return array{itemId: string, label: string, station: string, format: string, volume: int|null, price: int, count: int, garnishes: list<string>} */
    public function toArray(): array
    {
        return [
            'itemId' => $this->item->itemId,
            'label' => $this->item->label,
            'station' => $this->item->station->value,
            'format' => $this->item->format,
            'volume' => $this->item->volume,
            'price' => $this->item->price->cents,
            'count' => $this->count,
            'garnishes' => $this->garnishes,
        ];
    }

    /** @param array<mixed> $stored */
    public static function fromArray(array $stored): self
    {
        $string = static fn (string $key): string => \is_string($stored[$key] ?? null) ? $stored[$key] : '';
        $volume = $stored['volume'] ?? null;
        $count = $stored['count'] ?? 1;
        $garnishes = \is_array($stored['garnishes'] ?? null) ? array_values(array_filter($stored['garnishes'], 'is_string')) : [];

        return new self(
            new OrderedItem(
                itemId: $string('itemId'),
                label: $string('label'),
                station: Station::tryFrom($string('station')) ?? Station::Bar,
                format: $string('format'),
                volume: \is_int($volume) ? $volume : null,
                price: Money::cents(\is_int($stored['price'] ?? null) ? $stored['price'] : 0),
            ),
            \is_int($count) ? $count : 1,
            $garnishes,
        );
    }
}
