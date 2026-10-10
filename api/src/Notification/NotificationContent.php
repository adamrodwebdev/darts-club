<?php

namespace App\Notification;

final readonly class NotificationContent
{
    public function __construct(
        public Category $category,
        public string $title,
        public string $body,
        /** Chemin interne au front (« /evenements/42 »), jamais une URL externe */
        public ?string $link = null,
    ) {
        if ('' === trim($title) || mb_strlen($title) > 120) {
            throw new \InvalidArgumentException('Le titre doit faire entre 1 et 120 caractères.');
        }
        if (mb_strlen($body) > 2000) {
            throw new \InvalidArgumentException('Le message ne doit pas dépasser 2000 caractères.');
        }
        if (null !== $link && 1 !== preg_match('#^/(?!/)[\w\-./?=&%]*$#', $link)) {
            throw new \InvalidArgumentException('Le lien doit être un chemin interne.');
        }
    }

    /** « [Événement] Tournoi d'automne » */
    public function labelledTitle(): string
    {
        return \sprintf('[%s] %s', $this->category->label(), $this->title);
    }
}
