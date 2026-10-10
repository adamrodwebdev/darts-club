<?php

namespace App\Notification\Channel;

use App\Entity\Notification;
use App\Entity\User;
use App\Notification\Channel;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationContent;
use App\Notification\RealtimeTopics;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/** Centre de notifications : stockage + diffusion temps réel (Mercure, topic privé). */
final class InAppChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
        private readonly HubInterface $hub,
    ) {
    }

    public function channel(): Channel
    {
        return Channel::InApp;
    }

    public function send(User $recipient, NotificationContent $content): void
    {
        $notification = new Notification($recipient, $content);
        $this->em->persist($notification);
        $this->em->flush();

        $this->hub->publish(new Update(
            RealtimeTopics::notifications($recipient),
            json_encode([
                'notification' => $notification->toArray(),
                'unread' => $this->notifications->countUnread($recipient),
            ], \JSON_THROW_ON_ERROR),
            private: true,
        ));
    }
}
