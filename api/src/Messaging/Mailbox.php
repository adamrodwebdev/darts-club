<?php

namespace App\Messaging;

use App\Enum\Role;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Boîtes aux lettres du bureau : tout titulaire du rôle peut y répondre. */
enum Mailbox: string implements TranslatableInterface
{
    case Secretary = 'secretaire';
    case Treasurer = 'tresorier';
    case President = 'president';

    public function role(): Role
    {
        return match ($this) {
            self::Secretary => Role::Secretary,
            self::Treasurer => Role::Treasurer,
            self::President => Role::President,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Secretary => 'Secrétariat',
            self::Treasurer => 'Trésorerie',
            self::President => 'Présidence',
        };
    }

    /** Libellé dans la langue du lecteur (domaine « enums ») */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('mailbox.'.$this->value, [], 'enums', $locale);
    }
}
