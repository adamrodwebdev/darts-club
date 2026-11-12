<?php

declare(strict_types=1);

namespace App\ClubEvent;

use App\Entity\EventPhoto;

/**
 * Photos d'un événement partagées entre sa galerie et « Dans la presse » : une coupure de presse
 * n'apparaît qu'à un endroit. Les coupures vont de la plus ancienne parution à la plus récente,
 * celles sans date à la fin ; à date égale, dans l'ordre d'arrivée.
 */
final readonly class PressReview
{
    /**
     * @param list<EventPhoto> $gallery
     * @param list<EventPhoto> $clippings
     */
    private function __construct(
        public array $gallery,
        public array $clippings,
    ) {
    }

    /** @param list<EventPhoto> $photos les photos de l'événement, dans l'ordre d'arrivée */
    public static function of(array $photos): self
    {
        $gallery = [];
        $clippings = [];
        foreach ($photos as $photo) {
            if ($photo->isPressClipping()) {
                $clippings[] = $photo;
            } else {
                $gallery[] = $photo;
            }
        }
        // Tri stable depuis PHP 8 : à date égale, l'ordre d'arrivée reste
        usort($clippings, static fn (EventPhoto $a, EventPhoto $b): int => self::order($a) <=> self::order($b));

        return new self($gallery, $clippings);
    }

    /** @return array{bool, string} clé de tri : les coupures sans date après toutes les autres */
    private static function order(EventPhoto $clipping): array
    {
        $day = $clipping->pressDetails()?->publishedOn?->format(PressDetails::DAY_FORMAT);

        return [null === $day, $day ?? ''];
    }
}
