<?php

namespace App\Notification;

use App\Translation\InvalidValue;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Contenu rédigé (texte d'un adhérent ou déjà traduit), livré tel quel. */
final readonly class NotificationContent implements NotificationMessage
{
    public const TITLE_MAX = 120;
    public const BODY_MAX = 2000;

    public function __construct(
        public Category $category,
        public string $title,
        public string $body,
        /** Chemin interne au front (« /evenements/42 »), jamais une URL externe */
        public ?string $link = null,
        /** Libellé de la catégorie dans la langue du destinataire (posé par localize()) */
        public ?string $categoryLabel = null,
    ) {
        if ('' === trim($title) || mb_strlen($title) > self::TITLE_MAX) {
            throw new InvalidValue('Le titre doit faire entre 1 et %max% caractères.', ['%max%' => self::TITLE_MAX]);
        }
        if (mb_strlen($body) > self::BODY_MAX) {
            throw new InvalidValue('Le message ne doit pas dépasser %max% caractères.', ['%max%' => self::BODY_MAX]);
        }
        if (null !== $link && 1 !== preg_match('#^/(?!/)[\w\-./?=&%]*$#', $link)) {
            throw new InvalidValue('Le lien doit être un chemin interne.');
        }
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function localize(TranslatorInterface $translator, string $locale): self
    {
        return new self($this->category, $this->title, $this->body, $this->link, $this->category->trans($translator, $locale));
    }

    /** « [Événement] Soirée d'automne » */
    public function labelledTitle(): string
    {
        return \sprintf('[%s] %s', $this->categoryLabel ?? $this->category->label(), $this->title);
    }
}
