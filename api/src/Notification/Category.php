<?php

namespace App\Notification;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Labels des messages du bureau ; chaque adhérent règle ses canaux par catégorie. */
enum Category: string implements TranslatableInterface
{
    case Membership = 'cotisation';
    case Event = 'evenement';
    case Meeting = 'reunion';
    case Cancellation = 'annulation';
    case Announcement = 'information';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Membership => 'Cotisation',
            self::Event => 'Événement',
            self::Meeting => 'Réunion',
            self::Cancellation => 'Annulation',
            self::Announcement => 'Information',
            self::Message => 'Message',
        };
    }

    /** Libellé dans la langue du lecteur (domaine « enums ») */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('category.'.$this->value, [], 'enums', $locale);
    }
}
