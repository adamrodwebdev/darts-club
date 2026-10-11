<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Notification\Category;
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
        $this->notify($event->eventId, Category::Event, 'event.scheduled', null);
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

        $this->notify($event->eventId, Category::Event, 'event.rescheduled', Recipients::users(
            ...array_map(static fn ($u): Uuid => $u->getId(), $clubEvent->participants()),
        ));
    }

    #[AsEventListener]
    public function onCancelled(ClubEventCancelled $event): void
    {
        $this->notify($event->eventId, Category::Cancellation, 'event.cancelled', null);
        $clubEvent = $this->events->find($event->eventId);
        if (null !== $clubEvent) {
            $this->guests->notifyGuests($clubEvent, 'cancelled');
        }
    }

    /** @param string $titleKey clé du domaine « notifications », paramètre %title% */
    private function notify(Uuid $id, Category $category, string $titleKey, ?Recipients $recipients): void
    {
        $event = $this->events->find($id);
        if (null === $event) {
            return;
        }

        $this->notifier->notify(
            new TranslatedNotification($category, new TranslatableMessage($titleKey, ['%title%' => $event->getTitle()], 'notifications'), self::body($event), self::link($event)),
            $recipients ?? Recipients::allMembers(),
        );
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
