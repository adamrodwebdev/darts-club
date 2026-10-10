<?php

namespace App\Notification;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notification en attente de livraison : rendue dans la langue de chaque
 * destinataire juste avant l'envoi (centre de notifications, email, push).
 */
interface NotificationMessage
{
    public function category(): Category;

    public function localize(TranslatorInterface $translator, string $locale): NotificationContent;
}
