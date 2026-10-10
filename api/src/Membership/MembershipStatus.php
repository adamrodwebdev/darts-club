<?php

namespace App\Membership;

enum MembershipStatus: string
{
    case Paid = 'payee';
    case Partial = 'partielle';
    case Unpaid = 'impayee';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Payée',
            self::Partial => 'Partiellement payée',
            self::Unpaid => 'Non payée',
        };
    }
}
