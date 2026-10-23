<?php

namespace App\Tests\Unit;

use App\Security\ClientNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ClientNetworkTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function addresses(): iterable
    {
        yield 'IPv4 : l\'adresse' => ['203.0.113.7', '203.0.113.7'];
        yield 'IPv6 : son réseau /64' => ['2001:db8:7:9:1234:5678:9abc:def0', '2001:db8:7:9::/64'];
        yield 'IPv6 abrégée' => ['2001:db8::1', '2001:db8::/64'];
        yield 'IPv4 dans IPv6 : l\'adresse' => ['::ffff:203.0.113.7', '::ffff:203.0.113.7'];
    }

    #[DataProvider('addresses')]
    public function testTheKeyIsTheAddressOrItsNetwork(string $ip, string $key): void
    {
        self::assertSame($key, (string) ClientNetwork::of(Request::create('/', server: ['REMOTE_ADDR' => $ip])));
    }
}
