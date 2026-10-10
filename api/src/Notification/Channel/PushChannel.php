<?php

namespace App\Notification\Channel;

use App\Entity\User;
use App\Notification\Channel;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationContent;
use App\Notification\Push\PushResult;
use App\Notification\Push\PushSender;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PushChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly PushSender $sender,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function channel(): Channel
    {
        return Channel::Push;
    }

    public function send(User $recipient, NotificationContent $content): void
    {
        $payload = json_encode([
            'title' => $content->labelledTitle(),
            'body' => mb_strimwidth($content->body, 0, 180, '…'),
            'url' => $content->link ?? '/',
            'tag' => $content->category->value,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        $expired = false;
        foreach ($this->subscriptions->findByUser($recipient) as $subscription) {
            if (PushResult::Expired === $this->sender->send($subscription, $payload)) {
                $this->em->remove($subscription);
                $expired = true;
            }
        }

        if ($expired) {
            $this->em->flush();
        }
    }
}
