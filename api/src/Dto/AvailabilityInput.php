<?php

declare(strict_types=1);

namespace App\Dto;

use App\EventAccounts\ItemAvailability;
use App\EventAccounts\ItemServing;
use Symfony\Component\Validator\Constraints as Assert;

/** Article ou garnitures déclarés épuisés (ou remis à la carte) par la cuisine ou le bar. */
final class AvailabilityInput
{
    /** @param list<mixed> $garnishesOut */
    public function __construct(
        public readonly bool $soldOut = false,
        #[Assert\Count(max: ItemServing::GARNISHES_MAX)]
        public readonly array $garnishesOut = [],
    ) {
    }

    public function toAvailability(): ItemAvailability
    {
        return new ItemAvailability($this->soldOut, array_values(array_filter($this->garnishesOut, 'is_string')));
    }
}
