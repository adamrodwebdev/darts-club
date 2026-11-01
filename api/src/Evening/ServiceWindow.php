<?php

namespace App\Evening;

use App\ClubEvent\ClubTime;
use App\Entity\ClubEvent;

/**
 * Période où les écrans des équipes (accueil, bar, cuisine) sont ouverts : le jour de la soirée,
 * dès minuit à l'heure du club, jusqu'à six heures après la fin (rangement, dernières ventes).
 */
final readonly class ServiceWindow
{
    private const AFTER_END = 'PT6H';

    private function __construct(
        public \DateTimeImmutable $opensAt,
        public \DateTimeImmutable $closesAt,
    ) {
    }

    public static function of(ClubEvent $event): self
    {
        $day = $event->getStartsAt()->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE))->setTime(0, 0);

        return new self($day, $event->getEndsAt()->add(new \DateInterval(self::AFTER_END)));
    }

    public function isOpenAt(\DateTimeImmutable $now): bool
    {
        return $now >= $this->opensAt && $now <= $this->closesAt;
    }
}
