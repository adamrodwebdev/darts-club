<?php

namespace App\ClubEvent;

use App\Notification\Category;

/** Changement d'un événement annoncé aux adhérents : catégorie et titre de la notification */
enum EventChange
{
    case Scheduled;
    case Rescheduled;
    case Cancelled;

    public function category(): Category
    {
        return self::Cancelled === $this ? Category::Cancellation : Category::Event;
    }

    /** Clé du domaine « notifications », avec le paramètre %title% */
    public function titleKey(): string
    {
        return match ($this) {
            self::Scheduled => 'event.scheduled',
            self::Rescheduled => 'event.rescheduled',
            self::Cancelled => 'event.cancelled',
        };
    }
}
