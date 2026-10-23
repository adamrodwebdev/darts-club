<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\ClubEventRepository;
use App\Translation\LocalizedDate;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatableInterface;

/** Notifications du calendrier : nouvel événement, changement d'horaire, annulation. */
final class NotifyOnClubEventChanges
{
    public function __construct(
        private readonly ClubEventRepository $events,
        private readonly MemberNotifier $notifier,
        private readonly GuestMailer $guests,
    ) {
    }

    #[AsEventListener]
    public function onScheduled(ClubEventScheduled $event): void
    {
        $clubEvent = $this->events->find($event->eventId);
        if (null !== $clubEvent) {
            $this->notify($clubEvent, EventChange::Scheduled, Recipients::allMembers());
        }
    }

    /** Seuls les inscrits sont concernés par un changement d'horaire. */
    #[AsEventListener]
    public function onRescheduled(ClubEventRescheduled $event): void
    {
        $clubEvent = $this->events->find($event->eventId);
        if (null === $clubEvent) {
            return;
        }
        $this->guests->notifyGuests($clubEvent, 'rescheduled');
        if ([] === $clubEvent->participants()) {
            return;
        }

        $this->notify($clubEvent, EventChange::Rescheduled, Recipients::users(
            ...array_map(static fn ($u): Uuid => $u->getId(), $clubEvent->participants()),
        ));
    }

    #[AsEventListener]
    public function onCancelled(ClubEventCancelled $event): void
    {
        $clubEvent = $this->events->find($event->eventId);
        if (null !== $clubEvent) {
            $this->notify($clubEvent, EventChange::Cancelled, Recipients::allMembers());
            $this->guests->notifyGuests($clubEvent, 'cancelled');
        }
    }

    private function notify(ClubEvent $event, EventChange $change, Recipients $recipients): void
    {
        $this->notifier->notify(new TranslatedNotification(
            category: $change->category(),
            title: new TranslatableMessage($change->titleKey(), ['%title%' => $event->getTitle()], 'notifications'),
            body: self::body($event),
            link: self::link($event),
        ), $recipients);
    }

    /** « Vendredi 16 octobre à 20:00 · Bar du club » (+ motif d'annulation), dans la langue du lecteur */
    public static function body(ClubEvent $event): TranslatableInterface
    {
        $parameters = ['%date%' => new LocalizedDate($event->getStartsAt()), '%location%' => $event->getLocation()];

        return $event->isCancelled() && null !== $event->getCancelReason()
            ? new TranslatableMessage('event.body_cancelled', $parameters + ['%reason%' => $event->getCancelReason()], 'notifications')
            : new TranslatableMessage('event.body', $parameters, 'notifications');
    }

    public static function link(ClubEvent $event): string
    {
        return '/evenements/'.$event->getId()->toRfc4122();
    }
}
