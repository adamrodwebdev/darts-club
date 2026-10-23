<?php

namespace App\Tests\Double;

use App\Entity\PushSubscription;
use App\Notification\Push\PushResult;
use App\Notification\Push\PushSender;

final class FakePushSender implements PushSender
{
    /** @var list<array{endpoint: string, payload: string}> */
    public array $sent = [];

    /** @var list<string> points de terminaison à considérer comme expirés */
    public array $expiredEndpoints = [];

    public function send(PushSubscription $subscription, string $payload): PushResult
    {
        $url = $subscription->endpoint()->url;
        if (\in_array($url, $this->expiredEndpoints, true)) {
            return PushResult::Expired;
        }

        $this->sent[] = ['endpoint' => $url, 'payload' => $payload];

        return PushResult::Sent;
    }

    public function publicKey(): string
    {
        return 'test-public-key';
    }
}
