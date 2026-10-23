<?php

namespace App\Tests\Unit;

use App\Security\LoginTimingEqualizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

final class LoginTimingEqualizerTest extends TestCase
{
    public function testAnUnknownAddressCostsAHashLikeAWrongPassword(): void
    {
        self::assertSame(1, $this->hashesFor(new BadCredentialsException('Bad credentials.', 0, new UserNotFoundException())));
    }

    public function testAThrottledAttemptAnswersAtOnce(): void
    {
        self::assertSame(0, $this->hashesFor(new TooManyLoginAttemptsAuthenticationException()));
    }

    private function hashesFor(AuthenticationException $exception): int
    {
        $hasher = new class implements PasswordHasherInterface {
            public int $hashes = 0;

            public function hash(#[\SensitiveParameter] string $plainPassword): string
            {
                ++$this->hashes;

                return 'hash';
            }

            public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
            {
                return false;
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return false;
            }
        };
        $hashers = $this->createStub(PasswordHasherFactoryInterface::class);
        $hashers->method('getPasswordHasher')->willReturn($hasher);

        $request = Request::create('/api/auth/login', 'POST');
        $request->attributes->set('_route', 'auth_login');
        (new LoginTimingEqualizer($hashers))(new LoginFailureEvent($exception, $this->createStub(AuthenticatorInterface::class), $request, null, 'main'));

        return $hasher->hashes;
    }
}
