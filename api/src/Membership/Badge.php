<?php

namespace App\Membership;

use App\Enum\Role;

/** Badges affichés à côté du pseudo des adhérents. */
enum Badge: string
{
    case President = 'president';
    case Treasurer = 'tresorier';
    case Secretary = 'secretaire';
    case PaidUp = 'cotisant';

    public function label(): string
    {
        return match ($this) {
            self::President => 'Président',
            self::Treasurer => 'Trésorier',
            self::Secretary => 'Secrétaire',
            self::PaidUp => 'Cotisant',
        };
    }

    /** Badge de fonction correspondant au rôle attribué (l'admin technique n'en a pas). */
    public static function forRole(Role $role): ?self
    {
        return match ($role) {
            Role::President => self::President,
            Role::Treasurer => self::Treasurer,
            Role::Secretary => self::Secretary,
            default => null,
        };
    }
}
