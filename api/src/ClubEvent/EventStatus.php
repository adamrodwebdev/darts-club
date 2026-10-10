<?php

namespace App\ClubEvent;

enum EventStatus: string
{
    case Scheduled = 'prevu';
    case Cancelled = 'annule';
}
