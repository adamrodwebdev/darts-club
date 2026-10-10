<?php

namespace App\Tests\Integration;

use App\Repository\UserRepository;
use App\Security\OAuthUserResolver;
use App\Tests\ApiTestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class OAuthUserResolverTest extends ApiTestCase
{
    public function testCreatesVerifiedAccountWithoutVerificationEmail(): void
    {
        $user = $this->resolver()->resolve('google', 'g-123', 'New@Example.com', true);

        self::assertSame('new@example.com', $user->getEmail());
        self::assertTrue($user->isVerified());
        self::assertSame('', $user->getPassword(), 'Pas de mot de passe utilisable');
        self::assertEmailCount(0);
    }

    public function testLinksExistingAccountAndVerifiesIt(): void
    {
        $existing = $this->createUser('member@example.com', verified: false);

        $user = $this->resolver()->resolve('discord', 'd-42', 'member@example.com', true);

        self::assertTrue($user->getId()->equals($existing->getId()));
        self::assertTrue($user->isVerified());
    }

    public function testKnownIdentityReturnsSameUser(): void
    {
        $first = $this->resolver()->resolve('google', 'g-123', 'member@example.com', true);
        // Email changé côté fournisseur : l'identité prime
        $second = $this->resolver()->resolve('google', 'g-123', 'other@example.com', true);

        self::assertTrue($first->getId()->equals($second->getId()));
        self::assertCount(1, $this->service(UserRepository::class, UserRepository::class)->findAll());
    }

    public function testUnverifiedProviderEmailIsRefused(): void
    {
        $this->createUser('member@example.com');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->resolver()->resolve('discord', 'd-42', 'member@example.com', false);
    }

    private function resolver(): OAuthUserResolver
    {
        return $this->service(OAuthUserResolver::class, OAuthUserResolver::class);
    }
}
