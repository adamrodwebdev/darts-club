<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\User;
use App\Repository\ClubEventRepository;
use App\Repository\EventViewRepository;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Nombre de personnes uniques ayant vu l'annonce d'un événement, sans cookie
 * ni donnée identifiante (mesure d'audience exemptée de consentement, CNIL) :
 * - adhérent connecté : empreinte de son compte, compté exactement une fois ;
 * - visiteur anonyme : empreinte de son adresse IP et de son navigateur (estimation).
 * Les empreintes sont des HMAC dont la clé, propre à l'événement, est détruite
 * 30 jours après celui-ci ; le détail est supprimé au bout de 13 mois (totaux gardés).
 */
final class EventAudience
{
    /** Robots, aperçus de liens et navigateurs automatisés : non comptés */
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|headless|lighthouse|python|curl|wget/i';
    private const DETAIL_RETENTION = 'P13M';

    public function __construct(
        private readonly EventViewRepository $views,
        private readonly ClubEventRepository $events,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly TokenGenerator $tokens,
    ) {
    }

    /** @param bool $organizer les organisateurs ne faussent pas leurs propres statistiques */
    public function record(ClubEvent $event, ?User $viewer, bool $organizer, ?string $ip, string $userAgent): void
    {
        if ($organizer || 1 === preg_match(self::BOTS, $userAgent) || (null === $viewer && (null === $ip || '' === $userAgent))) {
            return;
        }

        $now = $this->clock->now();
        if ($event->isAudienceClosed($now)) {
            return;
        }
        if (null === $event->audienceKey()) {
            $event->openAudience($this->tokens->hex(32), $now);
            $this->em->flush();
        }
        $key = (string) $event->audienceKey();

        $subject = null !== $viewer ? 'member:'.$viewer->getId()->toRfc4122() : 'visitor:'.$ip.'|'.$userAgent;
        $day = $now->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE))->setTime(0, 0);
        $this->views->recordOnce($event, hash_hmac('sha256', $subject, $key), null !== $viewer, $day);
    }

    /**
     * @return array{unique: int, members: int, visitors: int, days: list<array{day: string, members: int, visitors: int}>, going: int, maybe: int, archived: bool, closed: bool}
     */
    public function report(ClubEvent $event): array
    {
        $days = $this->views->byDay($event);
        $archived = $event->archivedAudience();
        $members = $archived['members'] ?? array_sum(array_column($days, 'members'));
        $visitors = $archived['visitors'] ?? array_sum(array_column($days, 'visitors'));

        return [
            'unique' => $members + $visitors,
            'members' => $members,
            'visitors' => $visitors,
            'days' => $days,
            'going' => $event->goingCount(),
            'maybe' => $event->maybeCount(),
            'archived' => null !== $archived,
            'closed' => $event->isAudienceClosed($this->clock->now()),
        ];
    }

    /** Tâche de nuit : clés détruites après 30 jours, détail remplacé par les totaux après 13 mois. */
    public function purge(): void
    {
        $now = $this->clock->now();
        foreach ($this->events->withOpenAudienceEndedBefore($now->sub(new \DateInterval('P30D'))) as $event) {
            $event->closeAudience();
        }

        foreach ($this->events->withAudienceDetailEndedBefore($now->sub(new \DateInterval(self::DETAIL_RETENTION))) as $event) {
            $days = $this->views->byDay($event);
            $event->archiveAudience(array_sum(array_column($days, 'members')), array_sum(array_column($days, 'visitors')));
            $this->views->deleteFor($event);
        }

        $this->em->flush();
    }
}
