<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;

final class AuthTest extends ApiTestCase
{
    public function testVerifiedUserCanLogInAndFetchProfile(): void
    {
        $this->createUser('member@example.com');

        $this->login('member@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
        self::assertSame('member@example.com', $this->json()['email']);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_MEMBER'], $this->json()['roles']);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser('member@example.com');

        $this->login('member@example.com', 'wrong');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUnverifiedUserCannotLogIn(): void
    {
        $this->createUser('new@example.com', verified: false);

        $this->login('new@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Adresse email non vérifiée.', $this->json()['error']);
    }

    public function testProfileRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testCrossOriginWriteIsBlocked(): void
    {
        $this->createUser('member@example.com');

        $this->login('member@example.com', self::PASSWORD, origin: 'https://evil.example');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $this->createUser('member@example.com');

        for ($i = 0; $i < 5; ++$i) {
            $this->login('member@example.com', 'wrong');
        }

        $this->login('member@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        $response = $this->json();
        self::assertSame('too_many_attempts', $response['code']);
        self::assertIsString($response['error']);
        // Message traduit par Symfony (domaine « security »)
        self::assertStringContainsString('Trop de tentatives', $response['error']);
    }

    public function testLogoutEndsSession(): void
    {
        $this->createUser('member@example.com');
        $this->login('member@example.com', self::PASSWORD);
        self::assertResponseCookieValueSame('dc_session', '1');

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getCookieJar()->get('dc_session')?->getValue() ?? '');

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testPasswordIsHashedWithArgon2id(): void
    {
        $user = $this->createUser('member@example.com');

        self::assertStringStartsWith('$argon2id$', $user->getPassword());
    }
}
