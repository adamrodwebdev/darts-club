<?php

declare(strict_types=1);

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

    /** Le format de ce nom (« verre », « Verre ») ; null s'il n'est pas à la carte */
    public function find(string $name): ?SaleFormat
    {
        foreach ($this->all as $format) {
            if (mb_strtolower($format->name) === mb_strtolower(trim($name))) {
                return $format;
            }
        }

        return null;
    }

    /** Ventes d'un format augmentées (commande) ou diminuées (commande annulée), jamais sous zéro */
    public function withSold(string $name, int $delta): self
    {
        return new self(...array_map(
            static fn (SaleFormat $format): SaleFormat => mb_strtolower($format->name) === mb_strtolower(trim($name))
                ? new SaleFormat($format->name, $format->volume, $format->price, max(0, $format->sold + $delta))
                : $format,
            $this->all,
        ));
    }

    /**
     * Mêmes formats, mais les ventes déjà comptées par les commandes : modifier la carte pendant la
     * soirée ne remet jamais ces compteurs à la valeur d'un formulaire ouvert plus tôt.
     */
    public function keepingSoldOf(self $current): self
    {
        return new self(...array_map(
            static fn (SaleFormat $format): SaleFormat => new SaleFormat($format->name, $format->volume, $format->price, $current->find($format->name)->sold ?? $format->sold),
            $this->all,
        ));
    }

    /** Plus petite contenance vendue ; null si une contenance manque */
    public function smallestVolume(): ?int
    {
        $volumes = array_map(static fn (SaleFormat $format): ?int => $format->volume, $this->all);

        return \in_array(null, $volumes, true) ? null : min($volumes);
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
