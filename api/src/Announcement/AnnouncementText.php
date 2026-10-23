<?php

namespace App\Announcement;

use App\Translation\InvalidValue;

/** Titre et texte d'une annonce, validés ensemble (texte brut). */
final readonly class AnnouncementText
{
    public const TITLE_MAX = 120;
    public const BODY_MAX = 5000;

    private function __construct(
        public string $title,
        public string $body,
    ) {
    }

    public static function of(string $title, string $body): self
    {
        $title = trim($title);
        $body = trim(str_replace("\r\n", "\n", $body));

        if ('' === $title || mb_strlen($title) > self::TITLE_MAX) {
            throw new InvalidValue('Le titre doit faire entre 1 et %max% caractères.', ['%max%' => self::TITLE_MAX]);
        }
        if ('' === $body || mb_strlen($body) > self::BODY_MAX) {
            throw new InvalidValue('Le texte doit faire entre 1 et %max% caractères.', ['%max%' => self::BODY_MAX]);
        }

        return new self($title, $body);
    }
}
