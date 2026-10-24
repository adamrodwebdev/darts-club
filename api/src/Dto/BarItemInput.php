<?php

namespace App\Dto;

use App\EventAccounts\BarItemDraft;
use App\EventAccounts\ItemPrices;
use App\EventAccounts\ItemQuantities;
use App\Membership\Money;
use Symfony\Component\Validator\Constraints as Assert;

/** Article de la buvette (bilan d'une soirée) : quantités entières, prix décimaux « 0,95 ». */
final class BarItemInput
{
    private const MONEY = '/^\d{1,6}([.,]\d{1,2})?$/';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: BarItemDraft::LABEL_MAX)]
        public readonly string $label = '',

        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $planned = 0,

        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $bought = 0,

        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $returned = 0,

        #[Assert\Range(min: 0, max: ItemQuantities::MAX)]
        public readonly int $sold = 0,

        #[Assert\NotBlank]
        #[Assert\Regex(self::MONEY, message: 'Montant invalide.')]
        public readonly string $purchasePrice = '',

        #[Assert\NotBlank]
        #[Assert\Regex(self::MONEY, message: 'Montant invalide.')]
        public readonly string $salePrice = '',
    ) {
    }

    public function toDraft(): BarItemDraft
    {
        return new BarItemDraft(
            $this->label,
            new ItemQuantities(planned: $this->planned, bought: $this->bought, returned: $this->returned, sold: $this->sold),
            new ItemPrices(Money::fromDecimal($this->purchasePrice), Money::fromDecimal($this->salePrice)),
        );
    }
}
