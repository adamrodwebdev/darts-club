<?php

namespace App\Announcement;

use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\AnnouncementRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Nouvelle annonce : tous les adhérents sont prévenus (temps réel + email/push selon préférences). */
#[AsEventListener]
final class NotifyOnAnnouncementPublished
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
        private readonly MemberNotifier $notifier,
    ) {
    }

    public function __invoke(AnnouncementPublished $event): void
    {
        $announcement = $this->announcements->find($event->announcementId);
        if (null === $announcement) {
            return;
        }

        $this->notifier->notify(
            new NotificationContent(
                Category::Announcement,
                $announcement->getTitle(),
                mb_strimwidth($announcement->getBody(), 0, 300, '…'),
                '/infos',
            ),
            Recipients::allMembers(),
        );
    }
}
