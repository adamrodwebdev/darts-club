<?php

namespace App\Membership;

enum PaymentMethod: string
{
    case Cash = 'especes';
    case Cheque = 'cheque';
    case Transfer = 'virement';
    case Card = 'carte';
    case Online = 'en_ligne';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::Cheque => 'Chèque',
            self::Transfer => 'Virement',
            self::Card => 'Carte bancaire',
            self::Online => 'Paiement en ligne',
        };
    }
}
