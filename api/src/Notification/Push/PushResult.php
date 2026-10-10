<?php

namespace App\Notification\Push;

enum PushResult
{
    case Sent;
    /** Abonnement révoqué par le navigateur (404/410) : à supprimer */
    case Expired;
    case Failed;
}
