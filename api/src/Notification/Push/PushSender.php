<?php

namespace App\Notification\Push;

use App\Entity\PushSubscription;

/** Frontière vers le service Web Push (remplaçable en test). */
interface PushSender
{
    public function send(PushSubscription $subscription, string $payload): PushResult;

    /** Clé publique VAPID à transmettre au navigateur, null si non configurée. */
    public function publicKey(): ?string;
}
