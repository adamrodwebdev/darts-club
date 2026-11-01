<?php

namespace App\EventAccounts;

use App\Membership\Money;
use App\Translation\InvalidValue;

/**
 * Format vendu (verre, pichet, bouteille, portion), son prix et le nombre vendu. La contenance,
 * dans l'unité de stock de l'article (25 cl pour un verre), est facultative : sans elle, on compte
 * les ventes mais le reste en stock reste inconnu.
 */
final readonly class SaleFormat
{
    public const NAME_MAX = 30;
    public const SOLD_MAX = 100_000;

    public string $name;

    public function __construct(
        string $name,
        public ?int $volume,
        public Money $price,
        public int $sold = 0,
    ) {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::NAME_MAX) {
            throw new InvalidValue('Le nom d’un format fait entre 1 et %max% caractères.', ['%max%' => self::NAME_MAX]);
        }
        if (null !== $volume && ($volume < 1 || $volume > ContainerSpec::SIZE_MAX)) {
            throw new InvalidValue('Contenance invalide : de 1 à %max%.', ['%max%' => ContainerSpec::SIZE_MAX]);
        }
        if ($sold < 0 || $sold > self::SOLD_MAX) {
            throw new InvalidValue('Quantité invalide : de 0 à %max%.', ['%max%' => self::SOLD_MAX]);
        }
        $this->name = $name;
    }

    public function income(): Money
    {
        return $this->price->times($this->sold);
    }

    /** @return array{name: string, volume: int|null, price: int, sold: int} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'volume' => $this->volume, 'price' => $this->price->cents, 'sold' => $this->sold];
    }

    /** @param array<mixed> $stored */
    public static function fromArray(array $stored): self
    {
        $volume = $stored['volume'] ?? null;

        return new self(
            name: \is_string($stored['name'] ?? null) ? $stored['name'] : '?',
            volume: \is_int($volume) ? $volume : null,
            price: Money::cents(\is_int($stored['price'] ?? null) ? $stored['price'] : 0),
            sold: \is_int($stored['sold'] ?? null) ? $stored['sold'] : 0,
        );
    }
}
