<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\ClubEventRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

/** Notifications du calendrier : nouvel événement, changement d'horaire, annulation. */
final class NotifyOnClubEventChanges
{
    public function __construct(
        private readonly ClubEventRepository $events,
        private readonly MemberNotifier $notifier,
    ) {
    }

    #[AsEventListener]
    public function onScheduled(ClubEventScheduled $event): void
    {
        $this->notify($event->eventId, Category::Event, 'Nouvel événement : %s', null);
    }

    /** Seuls les inscrits sont concernés par un changement d'horaire. */
    #[AsEventListener]
    public function onRescheduled(ClubEventRescheduled $event): void
    {
        $clubEvent = $this->events->find($event->eventId);
        if (null === $clubEvent || [] === $clubEvent->participants()) {
            return;
        }

        $this->notify($event->eventId, Category::Event, 'Changement d\'horaire : %s', Recipients::users(
            ...array_map(static fn ($u): Uuid => $u->getId(), $clubEvent->participants()),
        ));
    }

    #[AsEventListener]
    public function onCancelled(ClubEventCancelled $event): void
    {
        $this->notify($event->eventId, Category::Cancellation, 'Annulé : %s', null);
    }

    private function notify(Uuid $id, Category $category, string $titleFormat, ?Recipients $recipients): void
    {
        $event = $this->events->find($id);
        if (null === $event) {
            return;
        }

        $this->notifier->notify(
            new NotificationContent($category, mb_substr(\sprintf($titleFormat, $event->getTitle()), 0, 120), self::body($event), self::link($event)),
            $recipients ?? Recipients::allMembers(),
        );
    }

    public static function body(ClubEvent $event): string
    {
        $body = ucfirst(ClubTime::format($event->getStartsAt())).' · '.$event->getLocation();

        return $event->isCancelled() && null !== $event->getCancelReason()
            ? $body."\nMotif : ".$event->getCancelReason()
            : $body;
    }

    public static function link(ClubEvent $event): string
    {
        return '/evenements/'.$event->getId()->toRfc4122();
    }
}
