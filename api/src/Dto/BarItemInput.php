<?php

declare(strict_types=1);

namespace App\Dto;

use App\EventAccounts\BarItemDraft;
use App\EventAccounts\ContainerSpec;
use App\EventAccounts\ItemQuantities;
use App\EventAccounts\ItemServing;
use App\EventAccounts\ItemStock;
use App\EventAccounts\SaleFormat;
use App\EventAccounts\SaleFormats;
use App\EventAccounts\Station;
use App\EventAccounts\StockUnit;
use App\Membership\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Article de la carte (bilan d'une soirée) : quantités entières en contenants, prix décimaux
 * « 0,95 », formats vendus [{ name, volume, price, sold }], garnitures.
 */
final class BarItemInput
{
    private const MONEY = '/^\d{1,6}([.,]\d{1,2})?$/';

    /**
     * @param list<mixed> $garnishes
     * @param list<mixed> $formats
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: BarItemDraft::LABEL_MAX)]
        public readonly string $label = '',
        #[Assert\Choice(callback: [self::class, 'stations'])]
        public readonly string $station = 'bar',
        #[Assert\Choice(callback: [self::class, 'units'])]
        public readonly string $unit = 'unite',
        #[Assert\Count(max: ItemServing::GARNISHES_MAX)]
        public readonly array $garnishes = [],
        public readonly bool $alcoholic = false,
        #[Assert\Length(max: ContainerSpec::NAME_MAX)]
        public readonly ?string $containerName = null,
        #[Assert\Range(min: 1, max: ContainerSpec::SIZE_MAX)]
        public readonly int $containerSize = 1,
        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $planned = 0,
        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $bought = 0,
        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $returned = 0,
        #[Assert\NotBlank]
        #[Assert\Regex(self::MONEY, message: 'Montant invalide.')]
        #[Assert\Length(max: Money::DECIMAL_TEXT_MAX)]
        public readonly string $purchasePrice = '',
        #[Assert\Count(min: 1, max: SaleFormats::MAX)]
        public readonly array $formats = [],
    ) {
    }

    /** @return list<string> */
    public static function stations(): array
    {
        return array_map(static fn (Station $station): string => $station->value, Station::cases());
    }

    /** @return list<string> */
    public static function units(): array
    {
        return array_map(static fn (StockUnit $unit): string => $unit->value, StockUnit::cases());
    }

    public function toDraft(): BarItemDraft
    {
        return new BarItemDraft(
            label: $this->label,
            serving: new ItemServing(
                station: Station::from($this->station),
                unit: StockUnit::from($this->unit),
                garnishes: array_map(static fn (mixed $garnish): string => \is_string($garnish) ? $garnish : '', $this->garnishes),
                alcoholic: $this->alcoholic,
            ),
            stock: new ItemStock(
                new ContainerSpec($this->containerName, $this->containerSize),
                new ItemQuantities(planned: $this->planned, bought: $this->bought, returned: $this->returned),
                Money::fromDecimal($this->purchasePrice),
            ),
            formats: new SaleFormats(...array_map(self::format(...), $this->formats)),
        );
    }

    private static function format(mixed $format): SaleFormat
    {
        $format = \is_array($format) ? $format : [];
        $volume = $format['volume'] ?? null;
        $sold = $format['sold'] ?? 0;

        return new SaleFormat(
            name: \is_string($format['name'] ?? null) ? $format['name'] : '',
            volume: \is_int($volume) ? $volume : null,
            price: Money::fromDecimal(\is_string($format['price'] ?? null) ? $format['price'] : ''),
            sold: \is_int($sold) ? $sold : -1,
        );
    }
}
