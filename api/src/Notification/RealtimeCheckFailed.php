<?php

declare(strict_types=1);

namespace App\Notification;

/** Le hub Mercure refuse les jetons de l'API ou ne livre pas une publication (raison lisible par l'exploitant). */
final class RealtimeCheckFailed extends \RuntimeException
{
}
