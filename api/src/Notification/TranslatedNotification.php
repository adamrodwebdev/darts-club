<?php

namespace App\Notification;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notification rédigée par l'application (domaine de traduction « notifications ») :
 * chaque destinataire la reçoit dans la langue de son compte.
 */
final readonly class TranslatedNotification implements NotificationMessage
{
    public function __construct(
        private Category $category,
        private TranslatableInterface $title,
        private TranslatableInterface $body,
        private ?string $link = null,
    ) {
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function localize(TranslatorInterface $translator, string $locale): NotificationContent
    {
        return (new NotificationContent(
            $this->category,
            mb_substr($this->title->trans($translator, $locale), 0, NotificationContent::TITLE_MAX),
            mb_substr($this->body->trans($translator, $locale), 0, NotificationContent::BODY_MAX),
            $this->link,
        ))->localize($translator, $locale);
    }
}
