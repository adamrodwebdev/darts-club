<?php

namespace App\ClubEvent;

use App\Membership\Season;

/** Recherche dans les archives : saison, texte (titre ou lieu), nombre maximal de résultats */
final readonly class ArchiveSearch
{
    public const QUERY_MAX = 80;

    public string $query;

    public function __construct(
        public ?Season $season = null,
        string $query = '',
        public int $limit = 100,
    ) {
        $this->query = mb_substr(trim($query), 0, self::QUERY_MAX);
    }

    /**
     * Depuis la requête (?season=2026-2027&q=…), saison vide = toutes.
     *
     * @throws \InvalidArgumentException saison mal écrite
     */
    public static function fromQuery(?string $season, string $query): self
    {
        return new self(null !== $season && '' !== $season ? Season::fromLabel($season) : null, $query);
    }
}
