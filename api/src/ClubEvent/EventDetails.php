<?php

namespace App\ClubEvent;

use App\Translation\InvalidValue;

/** Informations modifiables d'un événement, validées ensemble. */
final readonly class EventDetails
{
    public const TITLE_MAX = 120;
    public const DESCRIPTION_MAX = 5000;
    public const LOCATION_MAX = 160;
    private const MAX_DURATION = 'P7D';

    public function __construct(
        public string $title,
        public string $description,
        public string $location,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        /** Nombre de places, null = illimité */
        public ?int $capacity = null,
    ) {
        $title = trim($title);
        if ('' === $title || mb_strlen($title) > self::TITLE_MAX) {
            throw new InvalidValue('Le titre doit faire entre 1 et %max% caractères.', ['%max%' => self::TITLE_MAX]);
        }
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            throw new InvalidValue('La description ne doit pas dépasser %max% caractères.', ['%max%' => self::DESCRIPTION_MAX]);
        }
        if ('' === trim($location) || mb_strlen($location) > self::LOCATION_MAX) {
            throw new InvalidValue('Le lieu doit faire entre 1 et %max% caractères.', ['%max%' => self::LOCATION_MAX]);
        }
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('La fin doit être postérieure au début.');
        }
        if ($endsAt > $startsAt->add(new \DateInterval(self::MAX_DURATION))) {
            throw new \InvalidArgumentException('Un événement ne peut pas durer plus de 7 jours.');
        }
        if (null !== $capacity && ($capacity < 1 || $capacity > 1000)) {
            throw new \InvalidArgumentException('Le nombre de places doit être compris entre 1 et 1000.');
        }
    }

    public function sameSchedule(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): bool
    {
        return $this->startsAt == $startsAt && $this->endsAt == $endsAt;
    }
}
