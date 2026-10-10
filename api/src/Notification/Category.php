<?php

namespace App\Notification;

/** Labels des messages du bureau ; chaque adhérent règle ses canaux par catégorie. */
enum Category: string
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
}
