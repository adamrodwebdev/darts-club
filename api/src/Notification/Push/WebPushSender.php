<?php

namespace App\Notification\Push;

use App\Entity\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(PushSender::class)]
final class WebPushSender implements PushSender
{
    /**
     * Un service push qui tarde ne retient pas le worker (et les emails derrière lui) : ni redirection,
     * 5 s sans réponse ou 10 s en tout au plus, jamais vers une adresse privée.
     */
    private const HTTP_OPTIONS = ['max_redirects' => 0, 'timeout' => 5, 'max_duration' => 10];

    private ?WebPush $webPush = null;

    public function __construct(
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $vapidPublicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        #[\SensitiveParameter]
        private readonly string $vapidPrivateKey,
        #[Autowire('%env(VAPID_SUBJECT)%')]
        private readonly string $vapidSubject,
        private readonly HttpClientInterface $httpClient,
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

        try {
            $endpoint = $subscription->endpoint();
        } catch (\InvalidArgumentException) {
            // Enregistré avant la liste des services push des navigateurs : abonnement à supprimer
            return PushResult::Expired;
        }

        $report = $webPush->sendOneNotification(
            new Subscription($endpoint->url, $endpoint->publicKey, $endpoint->authToken),
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

        return $this->webPush ??= new WebPush(
            ['VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ]],
            client: new Psr18Client(new NoPrivateNetworkHttpClient($this->httpClient->withOptions(self::HTTP_OPTIONS))),
        );
    }
}
