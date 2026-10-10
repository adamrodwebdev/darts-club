<?php

namespace App\MessageHandler;

use App\Message\DeliverNotification;
use App\Notification\NotificationChannelInterface;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final class DeliverNotificationHandler
{
    /** @param iterable<NotificationChannelInterface> $channels */
    public function __construct(
        private readonly UserRepository $users,
        #[AutowireIterator('app.notification_channel')]
        private readonly iterable $channels,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(DeliverNotification $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user || !$user->isActive()) {
            return;
        }

        $preferences = $user->notificationPreferences();
        // Rendue une fois, dans la langue du destinataire, pour tous les canaux
        $content = $message->content->localize($this->translator, $user->language()->value);

        foreach ($this->channels as $channel) {
            if (!$preferences->allows($content->category, $channel->channel())) {
                continue;
            }

            // Un canal en panne (SMTP, push) ne doit pas bloquer les autres
            try {
                $channel->send($user, $content);
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
