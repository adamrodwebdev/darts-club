<?php

namespace App\ClubEvent;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\ClubEvent;
use App\Entity\User;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Cas d'usage du calendrier : planifier, modifier, annuler, répondre, résumer. L'archive a ses
 * propres services (EventPhotos, EventPosts), l'affiche aussi (EventPosters).
 */
final class ClubEvents
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly TokenGenerator $tokens,
        private readonly AuditTrail $audit,
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function schedule(User $organizer, EventDetails $details): ClubEvent
    {
        $event = ClubEvent::schedule($organizer, $details, $this->now());
        $this->giveAddressIfPublic($event);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function edit(ClubEvent $event, EventDetails $details): void
    {
        $event->edit($details, $this->now());
        $this->giveAddressIfPublic($event);
        $this->em->flush();
    }

    /** Tous les inscrits sont prévenus : l'annulation est inscrite au journal, avec son motif */
    public function cancel(ClubEvent $event, string $reason, User $organizer): void
    {
        $event->cancel($reason);
        $this->audit->record($organizer, new AuditEntry('event.cancel', $event->getTitle(), ['reason' => (string) $event->getCancelReason()]));
        $this->em->flush();
    }

    public function respond(ClubEvent $event, User $member, Attendance $answer): void
    {
        $event->respond($member, $answer, $this->now());
        $this->em->flush();
    }

    public function unregister(ClubEvent $event, User $member): void
    {
        $event->unregister($member, $this->now());
        $this->em->flush();
    }

    public function writeSummary(ClubEvent $event, string $summary): void
    {
        $event->writeSummary($summary);
        $this->em->flush();
    }

    /** Événement devenu public : adresse stable /e/{slug}, avec un suffixe aléatoire anti-collision */
    private function giveAddressIfPublic(ClubEvent $event): void
    {
        if ($event->needsPublicAddress()) {
            $event->assignPublicAddress($this->tokens->hex(3));
        }
    }
}
