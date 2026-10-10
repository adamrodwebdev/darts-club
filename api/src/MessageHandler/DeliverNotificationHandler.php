<?php

namespace App\MessageHandler;

use App\Message\DeliverNotification;
use App\Notification\NotificationChannelInterface;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeliverNotificationHandler
{
    /** @param iterable<NotificationChannelInterface> $channels */
    public function __construct(
        private readonly UserRepository $users,
        #[AutowireIterator('app.notification_channel')]
        private readonly iterable $channels,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliverNotification $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user || !$user->isActive()) {
            return;
        }

        $preferences = $user->notificationPreferences();

        foreach ($this->channels as $channel) {
            if (!$preferences->allows($message->content->category, $channel->channel())) {
                continue;
            }

            // Un canal en panne (SMTP, push) ne doit pas bloquer les autres
            try {
                $channel->send($user, $message->content);
            } catch (\Throwable $e) {
                $this->logger->error('Échec de notification sur le canal {channel} : {error}', [
                    'channel' => $channel->channel()->value,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }
    }
}
