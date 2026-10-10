<?php

namespace App\Notification;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Stratégie de livraison d'une notification sur un canal. */
#[AutoconfigureTag('app.notification_channel')]
interface NotificationChannelInterface
{
    public function channel(): Channel;

    public function send(User $recipient, NotificationContent $content): void;
}
