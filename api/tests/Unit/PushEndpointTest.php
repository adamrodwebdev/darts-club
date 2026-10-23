<?php

namespace App\Tests\Unit;

use App\Notification\Push\PushEndpoint;
use PHPUnit\Framework\TestCase;

final class PushEndpointTest extends TestCase
{
    public function testOnlyHttpsEndpointsAreAccepted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PushEndpoint('http://push.example.com/abc', 'key', 'auth');
    }

    public function testServiceHidesTheBrowserAddress(): void
    {
        $endpoint = new PushEndpoint('https://fcm.googleapis.com/fcm/send/abc123', 'key', 'auth');

        self::assertSame('fcm.googleapis.com', $endpoint->service());
    }
}
