<?php

namespace App\Tests\Unit;

use App\Notification\Push\PushEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PushEndpointTest extends TestCase
{
    public function testOnlyHttpsEndpointsAreAccepted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PushEndpoint('http://fcm.googleapis.com/fcm/send/abc', 'key', 'auth');
    }

    public function testServiceHidesTheBrowserAddress(): void
    {
        $endpoint = new PushEndpoint('https://fcm.googleapis.com/fcm/send/abc123', 'key', 'auth');

        self::assertSame('fcm.googleapis.com', $endpoint->service());
    }

    /** @return iterable<string, array{string}> */
    public static function browserServices(): iterable
    {
        yield 'Chrome' => ['https://fcm.googleapis.com/fcm/send/abc'];
        yield 'Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'];
        yield 'Edge' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'];
        yield 'Safari' => ['https://web.push.apple.com/abc'];
    }

    #[DataProvider('browserServices')]
    public function testBrowserPushServicesAreAccepted(string $url): void
    {
        self::assertSame($url, (new PushEndpoint($url, 'key', 'auth'))->url);
    }

    /** @return iterable<string, array{string}> */
    public static function otherHosts(): iterable
    {
        yield 'autre hôte' => ['https://attaquant.example/push'];
        yield 'adresse interne' => ['https://127.0.0.1/push'];
        yield 'nom qui imite un service' => ['https://fcm.googleapis.com.attaquant.example/push'];
        yield 'service dans les identifiants' => ['https://fcm.googleapis.com@attaquant.example/push'];
        yield 'port' => ['https://fcm.googleapis.com:8443/fcm/send/abc'];
    }

    #[DataProvider('otherHosts')]
    public function testAnyOtherHostIsRefused(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PushEndpoint($url, 'key', 'auth');
    }
}
