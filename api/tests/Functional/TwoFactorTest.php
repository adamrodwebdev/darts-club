<?php

namespace App\Tests\Functional;

use App\Enum\Role;
use App\Repository\UserRepository;
use App\Tests\ApiTestCase;
use Doctrine\DBAL\Connection;

final class TwoFactorTest extends ApiTestCase
{
    public function testEnrollmentShowsQrCodeThenActivatesWithBackupCodes(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->postJson('/api/me/2fa/setup', []);
        self::assertResponseIsSuccessful();
        $setup = $this->json();
        self::assertIsString($setup['secret']);
        self::assertIsString($setup['uri']);
        self::assertIsString($setup['qrCode']);
        self::assertStringStartsWith('otpauth://totp/Darts%20Club%3Aalice%40example.com', $setup['uri']);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $setup['qrCode']);

        $this->postJson('/api/me/2fa/confirm', ['code' => '000000']);
        self::assertResponseStatusCodeSame(422);

        $this->postJson('/api/me/2fa/confirm', ['code' => $this->totpCode($setup['secret'])]);
        self::assertResponseIsSuccessful();
        $codes = $this->json()['backupCodes'];
        self::assertIsArray($codes);
        self::assertCount(10, $codes);

        $this->client->request('GET', '/api/me');
        self::assertTrue($this->json()['twoFactorEnabled']);
    }

    public function testSecretIsEncryptedAtRest(): void
    {
        $alice = $this->createUser('alice@example.com');
        $secret = $this->enableTwoFactor($alice);

        $stored = $this->service('doctrine.dbal.default_connection', Connection::class)
            ->fetchOne('SELECT totp_secret FROM "user" WHERE email = ?', ['alice@example.com']);

        self::assertIsString($stored);
        self::assertStringStartsWith('v1:', $stored);
        self::assertStringNotContainsString($secret, $stored);
    }

    public function testLoginRequiresCodeOnceEnabled(): void
    {
        $alice = $this->createUser('alice@example.com');
        $secret = $this->enableTwoFactor($alice);

        $this->login('alice@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(202);
        self::assertTrue($this->json()['twoFactorRequired']);

        // Mot de passe seul : aucun accès
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);

        $this->postJson('/api/auth/2fa/check', ['code' => '123456']);
        self::assertResponseStatusCodeSame(401);

        $this->postJson('/api/auth/2fa/check', ['code' => $this->totpCode($secret)]);
        self::assertResponseIsSuccessful();
        self::assertSame('alice@example.com', $this->json()['email']);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testBackupCodeWorksOnlyOnce(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->enableTwoFactor($alice, ['abcde-12345']);

        $this->login('alice@example.com', self::PASSWORD);
        $this->postJson('/api/auth/2fa/check', ['code' => 'ABCDE 12345']);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);
        $this->postJson('/api/auth/2fa/check', ['code' => 'abcde-12345']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testCodeCheckIsThrottled(): void
    {
        $alice = $this->createUser('alice@example.com');
        $secret = $this->enableTwoFactor($alice);
        $this->login('alice@example.com', self::PASSWORD);

        for ($i = 0; $i < 5; ++$i) {
            $this->postJson('/api/auth/2fa/check', ['code' => '000000']);
        }

        $this->postJson('/api/auth/2fa/check', ['code' => $this->totpCode($secret)]);
        self::assertResponseStatusCodeSame(429);
    }

    public function testBoardMemberMustEnableTwoFactor(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();

        $this->login('treasurer@example.com', self::PASSWORD);
        $this->client->request('GET', '/api/me');
        self::assertTrue($this->json()['twoFactorSetupRequired']);

        // Fonctions du club bloquées, configuration du compte autorisée
        $this->client->request('GET', '/api/conversations');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('two_factor_setup_required', $this->json()['code']);

        $this->postJson('/api/me/2fa/setup', []);
        self::assertResponseIsSuccessful();
    }

    public function testDisableRequiresValidCode(): void
    {
        $alice = $this->createUser('alice@example.com');
        $secret = $this->enableTwoFactor($alice);
        $this->loginWithTwoFactor('alice@example.com', $secret);

        $this->request('DELETE', '/api/me/2fa', ['code' => '000000']);
        self::assertResponseStatusCodeSame(422);

        $this->request('DELETE', '/api/me/2fa', ['code' => $this->totpCode($secret)]);
        self::assertResponseStatusCodeSame(204);

        $this->entityManager()->clear();
        $user = $this->service(UserRepository::class, UserRepository::class)->findOneBy(['email' => 'alice@example.com']);
        self::assertNotNull($user);
        self::assertFalse($user->hasTwoFactor());
    }

    /** @param array<mixed> $payload */
    private function request(string $method, string $uri, array $payload): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
