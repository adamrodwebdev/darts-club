<?php

namespace App\Notification\Push;

use App\Entity\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(PushSender::class)]
final class WebPushSender implements PushSender
{
    private ?WebPush $webPush = null;

    public function __construct(
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $vapidPublicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        #[\SensitiveParameter]
        private readonly string $vapidPrivateKey,
        #[Autowire('%env(VAPID_SUBJECT)%')]
        private readonly string $vapidSubject,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publicKey(): ?string
    {
        return '' !== $this->vapidPublicKey ? $this->vapidPublicKey : null;
    }

    public function send(PushSubscription $subscription, string $payload): PushResult
    {
        $webPush = $this->webPush();
        if (null === $webPush) {
            return PushResult::Failed;
        }

        $report = $webPush->sendOneNotification(
            new Subscription($subscription->getEndpoint(), $subscription->getPublicKey(), $subscription->getAuthToken()),
            $payload,
            ['TTL' => 86400, 'urgency' => 'normal'],
        );

        if ($report->isSuccess()) {
            return PushResult::Sent;
        }
        if ($report->isSubscriptionExpired()) {
            return PushResult::Expired;
        }

        $this->logger->warning('Échec Web Push : {reason}', ['reason' => $report->getReason()]);

        return PushResult::Failed;
    }

    private function webPush(): ?WebPush
    {
        if (null === $this->publicKey() || '' === $this->vapidPrivateKey) {
            $this->logger->notice('Web Push non configuré (clés VAPID absentes).');

            return null;
        }

        return $this->webPush ??= new WebPush(['VAPID' => [
            'subject' => $this->vapidSubject,
            'publicKey' => $this->vapidPublicKey,
            'privateKey' => $this->vapidPrivateKey,
        ]]);
    }
}
