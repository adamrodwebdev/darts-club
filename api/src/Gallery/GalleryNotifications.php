<?php

namespace App\Gallery;

use App\Entity\User;
use App\Event\MediaRemovalRequested;
use App\Messaging\Mailbox;
use App\Messaging\MailboxAccess;
use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\ClubEventRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Translation\TranslatableMessage;

/** Demande de retrait : la présidence (président et administrateur) est prévenue, avec le lien de l'événement. */
final class GalleryNotifications
{
    public function __construct(
        private readonly ClubEventRepository $events,
        private readonly MailboxAccess $mailboxAccess,
        private readonly MemberNotifier $notifier,
    ) {
    }

    #[AsEventListener]
    public function onRemovalRequested(MediaRemovalRequested $request): void
    {
        $event = $this->events->find($request->eventId);
        $moderators = array_map(static fn (User $user) => $user->getId(), $this->mailboxAccess->holdersOf(Mailbox::President));
        if (null === $event || [] === $moderators) {
            return;
        }

        $this->notifier->notify(
            new TranslatedNotification(
                category: Category::Event,
                title: new TranslatableMessage('gallery.removal.title', ['%event%' => $event->getTitle()], 'notifications'),
                body: new TranslatableMessage($request->kind->removalNoticeKey(), ['%name%' => $request->requesterName], 'notifications'),
                link: '/evenements/'.$event->getId()->toRfc4122(),
            ),
            Recipients::users(...$moderators),
        );
    }
}
