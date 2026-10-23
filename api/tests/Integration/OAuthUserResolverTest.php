<?php

namespace App\Tests\Integration;

use App\Repository\UserRepository;
use App\Security\OAuthProfile;
use App\Security\OAuthUserResolver;
use App\Tests\ApiTestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class OAuthUserResolverTest extends ApiTestCase
{
    public function testCreatesVerifiedAccountWithoutVerificationEmail(): void
    {
        $user = $this->resolver()->resolve(new OAuthProfile(provider: 'google', subject: 'g-123', email: 'New@Example.com', emailVerified: true));

        self::assertSame('new@example.com', $user->getEmail());
        self::assertTrue($user->isVerified());
        self::assertSame('', $user->getPassword(), 'Pas de mot de passe utilisable');
        self::assertEmailCount(0);
    }

    public function testLinksExistingAccountAndVerifiesIt(): void
    {
        $existing = $this->createUser('member@example.com', verified: false);

        $user = $this->resolver()->resolve(new OAuthProfile(provider: 'discord', subject: 'd-42', email: 'member@example.com', emailVerified: true));

        self::assertTrue($user->getId()->equals($existing->getId()));
        self::assertTrue($user->isVerified());
    }

    public function testKnownIdentityReturnsSameUser(): void
    {
        $first = $this->resolver()->resolve(new OAuthProfile(provider: 'google', subject: 'g-123', email: 'member@example.com', emailVerified: true));
        // Email changé côté fournisseur : l'identité prime
        $second = $this->resolver()->resolve(new OAuthProfile(provider: 'google', subject: 'g-123', email: 'other@example.com', emailVerified: true));

        self::assertTrue($first->getId()->equals($second->getId()));
        self::assertCount(1, $this->service(UserRepository::class, UserRepository::class)->findAll());
    }

    public function testUnverifiedProviderEmailIsRefused(): void
    {
        $this->createUser('member@example.com');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->resolver()->resolve(new OAuthProfile(provider: 'discord', subject: 'd-42', email: 'member@example.com', emailVerified: false));
    }

    private function resolver(): OAuthUserResolver
    {
        return $this->service(OAuthUserResolver::class, OAuthUserResolver::class);
    }
}
