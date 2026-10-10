<?php

namespace App\Admin;

use App\Notification\Category;

/** Labels des messages du président, rattachés aux catégories de notification. */
enum BroadcastLabel: string
{
    case Membership = 'cotisation';
    case Event = 'evenement';
    case Meeting = 'reunion';
    case Cancellation = 'annulation';

    public function category(): Category
    {
        return match ($this) {
            self::Membership => Category::Membership,
            self::Event => Category::Event,
            self::Meeting => Category::Meeting,
            self::Cancellation => Category::Cancellation,
        };
    }
}
