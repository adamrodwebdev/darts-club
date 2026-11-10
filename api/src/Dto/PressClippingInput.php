<?php

declare(strict_types=1);

namespace App\Dto;

use App\ClubEvent\PressDetails;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Détails d'une coupure de presse, tous facultatifs : un champ absent, null ou vide n'est pas
 * renseigné. La date de parution s'écrit AAAA-MM-JJ.
 */
final class PressClippingInput
{
    public function __construct(
        #[Assert\Length(max: PressDetails::OUTLET_MAX)]
        public readonly ?string $outlet = null,

        #[Assert\Date]
        public readonly ?string $publishedOn = null,

        #[Assert\Length(max: PressDetails::TITLE_MAX)]
        public readonly ?string $title = null,

        #[Assert\Length(max: PressDetails::CREDIT_MAX)]
        public readonly ?string $credit = null,
    ) {
    }

    public function toDetails(): PressDetails
    {
        return new PressDetails(
            outlet: $this->outlet,
            publishedOn: PressDetails::day($this->publishedOn),
            title: $this->title,
            credit: $this->credit,
        );
    }
}
