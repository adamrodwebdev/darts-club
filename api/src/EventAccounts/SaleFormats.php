<?php

namespace App\EventAccounts;

use App\Membership\Money;
use App\Translation\InvalidValue;

/** Formats vendus d'un article : un au moins, six au plus, chacun sous un nom différent. */
final readonly class SaleFormats
{
    public const MAX = 6;

    /** @var list<SaleFormat> */
    public array $all;

    public function __construct(SaleFormat ...$formats)
    {
        if ([] === $formats || \count($formats) > self::MAX) {
            throw new InvalidValue('Un article se vend en 1 à %max% formats.', ['%max%' => self::MAX]);
        }
        $names = array_map(static fn (SaleFormat $format): string => mb_strtolower($format->name), $formats);
        if (\count(array_unique($names)) !== \count($names)) {
            throw new InvalidValue('Deux formats portent le même nom.');
        }
        $this->all = array_values($formats);
    }

    public function first(): SaleFormat
    {
        return $this->all[0];
    }

    public function income(): Money
    {
        return array_reduce($this->all, static fn (Money $sum, SaleFormat $format): Money => $sum->add($format->income()), Money::zero());
    }

    /** Servi en unités de stock (centilitres, unités, portions) ; null si une contenance manque */
    public function served(): ?int
    {
        $served = 0;
        foreach ($this->all as $format) {
            if (null === $format->volume) {
                return null;
            }
            $served += $format->sold * $format->volume;
        }

        return $served;
    }

    public function soldCount(): int
    {
        return array_sum(array_map(static fn (SaleFormat $format): int => $format->sold, $this->all));
    }

    /** @return list<array{name: string, volume: int|null, price: int, sold: int}> */
    public function toArray(): array
    {
        return array_map(static fn (SaleFormat $format): array => $format->toArray(), $this->all);
    }

    /** @param array<mixed> $stored */
    public static function fromArray(array $stored): self
    {
        return new self(...array_map(static fn (mixed $format): SaleFormat => SaleFormat::fromArray(\is_array($format) ? $format : []), array_values($stored)));
    }
}
