<?php

declare(strict_types=1);

namespace App\ClubEvent;

use App\Translation\InvalidValue;

/**
 * Ce qu'une coupure de presse dit sous son image : le journal, la date de parution, le titre de
 * l'article et le crédit (« Texte et photo : … »). Les quatre sont facultatifs : un texte vide
 * n'est pas renseigné (null). La date est un jour du calendrier, sans heure ni fuseau.
 */
final readonly class PressDetails
{
    public const OUTLET_MAX = 80;
    public const TITLE_MAX = 200;
    public const CREDIT_MAX = 120;
    /** Un jour écrit à la manière ISO 8601 : AAAA-MM-JJ */
    public const DAY_FORMAT = 'Y-m-d';
    /** Parutions acceptées : un journal du XXe ou du XXIe siècle (la base refuse l'an 0) */
    private const FIRST_DAY = '1900-01-01';
    private const LAST_DAY = '2099-12-31';

    public ?string $outlet;
    public ?\DateTimeImmutable $publishedOn;
    public ?string $title;
    public ?string $credit;

    public function __construct(
        ?string $outlet = null,
        ?\DateTimeImmutable $publishedOn = null,
        ?string $title = null,
        ?string $credit = null,
    ) {
        $this->outlet = self::filled($outlet);
        $this->title = self::filled($title);
        $this->credit = self::filled($credit);
        if (null !== $this->outlet && mb_strlen($this->outlet) > self::OUTLET_MAX) {
            throw new InvalidValue('Le nom du journal ne doit pas dépasser %max% caractères.', ['%max%' => self::OUTLET_MAX]);
        }
        if (null !== $this->title && mb_strlen($this->title) > self::TITLE_MAX) {
            throw new InvalidValue('Le titre de l’article ne doit pas dépasser %max% caractères.', ['%max%' => self::TITLE_MAX]);
        }
        if (null !== $this->credit && mb_strlen($this->credit) > self::CREDIT_MAX) {
            throw new InvalidValue('Le crédit ne doit pas dépasser %max% caractères.', ['%max%' => self::CREDIT_MAX]);
        }

        $this->publishedOn = $publishedOn?->setTime(0, 0);
        $day = $this->publishedOn?->format(self::DAY_FORMAT);
        // Comparaison de jours AAAA-MM-JJ : l'ordre des textes est celui du calendrier
        if (null !== $day && (strcmp($day, self::FIRST_DAY) < 0 || strcmp($day, self::LAST_DAY) > 0)) {
            throw new InvalidValue('La date de parution doit être comprise entre 1900 et 2099.');
        }
    }

    /**
     * Date de parution reçue en texte : un vrai jour du calendrier écrit AAAA-MM-JJ, en entier
     * (« 2026-02-30 », « 2026-2-3 » ou « 2026-11-03T10:00 » sont refusés, jamais corrigés) ;
     * null ou vide = pas de date.
     */
    public static function day(?string $text): ?\DateTimeImmutable
    {
        if (null === $text || '' === $text) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!'.self::DAY_FORMAT, $text);
        if (false === $day || $day->format(self::DAY_FORMAT) !== $text) {
            throw new InvalidValue('Date de parution invalide : AAAA-MM-JJ attendu.');
        }

        return $day;
    }

    /** @return array{outlet: ?string, publishedOn: ?string, title: ?string, credit: ?string} */
    public function toArray(): array
    {
        return [
            'outlet' => $this->outlet,
            'publishedOn' => $this->publishedOn?->format(self::DAY_FORMAT),
            'title' => $this->title,
            'credit' => $this->credit,
        ];
    }

    private static function filled(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' === $text ? null : $text;
    }
}
