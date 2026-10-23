<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Export iCalendar (RFC 5545) : fichier d'un événement ou flux d'abonnement. */
final class IcsCalendar
{
    public function __construct(
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param list<ClubEvent> $events
     * @param string          $locale langue de l'abonné (mentions d'annulation)
     */
    public function render(array $events, string $name, string $locale = 'fr'): string
    {
        $now = $this->clock->now();
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Skull Darts 71//Calendrier//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($name),
            'X-WR-TIMEZONE:'.ClubTime::TIMEZONE,
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
        ];

        foreach ($events as $event) {
            $description = $event->getDescription();
            if ($event->isCancelled()) {
                $description = $this->translator->trans('calendar.cancelled_reason', ['%reason%' => (string) $event->getCancelReason()], 'exports', $locale)."\n\n".$description;
            }

            array_push(
                $lines,
                'BEGIN:VEVENT',
                'UID:'.$event->getId()->toRfc4122().'@darts-club',
                'DTSTAMP:'.self::utc($now),
                'DTSTART:'.self::utc($event->getStartsAt()),
                'DTEND:'.self::utc($event->getEndsAt()),
                'SUMMARY:'.self::escape(($event->isCancelled() ? $this->translator->trans('calendar.cancelled_prefix', [], 'exports', $locale).' ' : '').$event->getTitle()),
                'LOCATION:'.self::escape($event->getLocation()),
                'DESCRIPTION:'.self::escape(trim($description)),
                'URL:'.rtrim($this->frontendUrl, '/').NotifyOnClubEventChanges::link($event),
                'STATUS:'.($event->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
                'END:VEVENT',
            );
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function utc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** Échappement des valeurs texte (RFC 5545 § 3.3.11). */
    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', ''], $text);
    }

    /** Lignes de 75 octets maximum, sans couper un caractère UTF-8. */
    private static function fold(string $line): string
    {
        $out = '';
        $length = 0;
        foreach (mb_str_split($line) as $char) {
            $bytes = \strlen($char);
            if ($length + $bytes > 75) {
                $out .= "\r\n ";
                $length = 1;
            }
            $out .= $char;
            $length += $bytes;
        }

        return $out;
    }
}
