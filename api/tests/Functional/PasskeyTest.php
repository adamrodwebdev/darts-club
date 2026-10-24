<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Tests\ApiTestCase;
use App\Tests\Double\SoftwareAuthenticator;
use Symfony\Component\Clock\MockClock;

/** Clés d'accès (passkeys) : création, connexion, deuxième étape du bureau, réinitialisation. */
final class PasskeyTest extends ApiTestCase
{
    public function testAMemberAddsAPasskeyThenLogsInWithItAlone(): void
    {
        $this->createUser('alice@example.com');
        $device = new SoftwareAuthenticator();
        $this->login('alice@example.com', self::PASSWORD);

        $this->addPasskey($device);
        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, (array) $this->json()['items']);
        $alert = self::getMailerMessage();
        self::assertNotNull($alert);
        self::assertEmailSubjectContains($alert, 'Nouvelle clé d\'accès');

        $this->logout();
        $this->passkeyLogin($device);
        self::assertResponseIsSuccessful();
        self::assertSame('alice@example.com', $this->json()['email']);
        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testASignatureServesOnceAndOnlyOnTheSite(): void
    {
        $this->createUser('alice@example.com');
        $device = new SoftwareAuthenticator();
        $this->login('alice@example.com', self::PASSWORD);
        $this->addPasskey($device);
        $this->logout();

        $this->postJson('/api/auth/passkey/options', []);
        $assertion = $device->get($this->json());
        $this->postRaw('/api/auth/passkey/login', $assertion);
        self::assertResponseIsSuccessful();

        // Rejouée par un intrus : le défi a déjà servi
        $this->logout();
        $this->postRaw('/api/auth/passkey/login', $assertion);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('passkey_refused', $this->json()['code']);

        // Même clé, signée pour un faux site : refusée
        $this->postJson('/api/auth/passkey/options', []);
        $this->postRaw('/api/auth/passkey/login', $device->on('https://skulldarts71.example')->get($this->json()));
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheBoardConfirmsWithItsPasskeyInsteadOfTheCode(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('treasurer@example.com', $this->enableTwoFactor($treasurer));
        $device = new SoftwareAuthenticator();

        // La clé créée, le code à 6 chiffres n'est plus demandé
        $this->addPasskey($device);
        $this->client->request('GET', '/api/me');
        self::assertFalse($this->json()['twoFactorEnabled']);
        self::assertTrue($this->json()['hasPasskey']);

        $this->logout();
        $this->login('treasurer@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(202);
        self::assertSame('passkey', $this->json()['method']);
        $this->postJson('/api/auth/2fa/passkey', []);
        self::assertCount(1, (array) $this->json()['allowCredentials']);
        $this->postJson('/api/auth/2fa/check', ['code' => $device->get($this->json())]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/treasury/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testABoardMemberLoggingInWithTheirPasskeyIsNotAskedTwice(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        $device = new SoftwareAuthenticator();
        $this->login('treasurer@example.com', self::PASSWORD);
        $this->addPasskey($device);

        $this->logout();
        $this->passkeyLogin($device);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('treasurer@example.com', $this->json()['email']);
        $this->client->request('GET', '/api/treasury/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testTheAdministratorKeepsTheirCodeAfterAPasskeyLogin(): void
    {
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $this->entityManager()->flush();
        $device = new SoftwareAuthenticator();
        $this->loginWithTwoFactor('admin@example.com', $this->enableTwoFactor($admin));
        $this->addPasskey($device);
        self::assertResponseStatusCodeSame(201);

        $this->logout();
        $this->passkeyLogin($device);
        self::assertResponseStatusCodeSame(202);
        self::assertSame('totp', $this->json()['method']);
    }

    public function testABoardMemberWithNeitherPasskeyNorCodeIsAskedToCreateOne(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        $this->login('treasurer@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/me');
        self::assertTrue($this->json()['passkeySetupRequired']);
        self::assertFalse($this->json()['twoFactorSetupRequired']);
        $this->client->request('GET', '/api/conversations');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('passkey_setup_required', $this->json()['code']);

        $this->addPasskey(new SoftwareAuthenticator());
        self::assertResponseStatusCodeSame(201);
        $this->client->request('GET', '/api/conversations');
        self::assertResponseIsSuccessful();
    }

    public function testKeysAreAddedOnlyRightAfterALogin(): void
    {
        $this->createUser('alice@example.com');
        $this->client->disableReboot();
        $this->login('alice@example.com', self::PASSWORD);

        $clock = $this->service('clock', MockClock::class);
        $clock->sleep(601);
        $this->postJson('/api/me/passkeys/options', []);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('reauthentication_required', $this->json()['code']);
    }

    public function testTheAdministratorResetsALostDeviceAndKeepsTheirOwnCode(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $this->entityManager()->flush();
        $adminSecret = $this->enableTwoFactor($admin);

        $this->login('treasurer@example.com', self::PASSWORD);
        $this->addPasskey(new SoftwareAuthenticator());
        $treasurerSession = $this->client->getCookieJar()->all();

        // L'administrateur garde son code, même avec une clé d'accès
        $this->client->getCookieJar()->clear();
        $this->login('admin@example.com', self::PASSWORD);
        self::assertSame('totp', $this->json()['method']);
        $this->postJson('/api/auth/2fa/check', ['code' => $this->totpCode($adminSecret)]);
        self::assertResponseIsSuccessful();

        $this->postJson("/api/admin/members/{$treasurer->getId()->toRfc4122()}/passkeys/reset", []);
        self::assertResponseStatusCodeSame(204);
        $notice = self::getMailerMessage();
        self::assertNotNull($notice);
        self::assertEmailSubjectContains($notice, 'Ton accès a été réinitialisé');
        self::assertSame(['member.passkeys_reset'], array_column($this->auditTrail(), 'action'));

        // Les sessions du trésorier sont fermées ; il recréera une clé à sa prochaine connexion
        $this->client->getCookieJar()->clear();
        foreach ($treasurerSession as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->entityManager()->clear();
        self::assertFalse($this->entityManager()->find(User::class, $treasurer->getId())?->hasPasskey());
    }

    private function addPasskey(SoftwareAuthenticator $device): void
    {
        $this->postJson('/api/me/passkeys/options', []);
        self::assertResponseIsSuccessful();
        $this->postRaw('/api/me/passkeys', $device->create($this->json()));
    }

    private function passkeyLogin(SoftwareAuthenticator $device): void
    {
        $this->postJson('/api/auth/passkey/options', []);
        self::assertResponseIsSuccessful();
        $this->postRaw('/api/auth/passkey/login', $device->get($this->json()));
    }

    private function postRaw(string $uri, string $json): void
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: $json);
    }

    private function logout(): void
    {
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
    }
}
