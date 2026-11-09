<?php

declare(strict_types=1);

namespace App\ClubEvent;

enum EventStatus: string
{
    case Scheduled = 'prevu';
    case Cancelled = 'annule';
}
