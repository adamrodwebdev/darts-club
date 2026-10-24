<?php

namespace App\Documents;

/**
 * Série de billets d'entrée à imprimer : chaque billet et sa souche portent l'organisateur, la soirée,
 * un numéro d'une série continue, la catégorie et le prix.
 */
final readonly class TicketSeries
{
    public const MAX_COUNT = 500;
    public const MAX_NUMBER = 999_999;

    public int $first;
    public int $count;

    public function __construct(
        public string $organizer,
        public string $event,
        public string $category,
        public string $price,
        int $first,
        int $count,
    ) {
        $this->first = max(1, min(self::MAX_NUMBER, $first));
        $this->count = max(1, min(self::MAX_COUNT, $count, self::MAX_NUMBER - $this->first + 1));
    }

    public function last(): int
    {
        return $this->first + $this->count - 1;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'organizer' => $this->organizer,
            'event' => $this->event,
            'category' => $this->category,
            'price' => $this->price,
            'first' => $this->first,
            'count' => $this->count,
        ];
    }
}
