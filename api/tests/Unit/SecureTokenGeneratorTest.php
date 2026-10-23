<?php

namespace App\Tests\Unit;

use App\Security\SecureTokenGenerator;
use PHPUnit\Framework\TestCase;

final class SecureTokenGeneratorTest extends TestCase
{
    public function testTokensHaveTheRequestedStrength(): void
    {
        $tokens = new SecureTokenGenerator();

        self::assertSame(24, \strlen($tokens->bytes(24)));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $tokens->hex(16));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $tokens->urlSafe(32));
        self::assertNotSame($tokens->hex(16), $tokens->hex(16));
    }
}
