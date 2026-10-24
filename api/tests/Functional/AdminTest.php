<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Tests\ApiTestCase;

final class AdminTest extends ApiTestCase
{
    private User $president;

    public function testPresidentAssignsBoardRolesAndActionIsAudited(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->loginAsPresident();

        $this->put("/api/admin/members/{$alice->getId()->toRfc4122()}/roles", ['roles' => ['tresorier']]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/admin/members?q=alice');
        self::assertSame(['tresorier'], $this->dig($this->json(), 'items', 0, 'roles'));
        self::assertSame([['id' => 'tresorier', 'label' => 'Trésorier']], $this->dig($this->json(), 'items', 0, 'badges'));

        $this->client->request('GET', '/api/admin/audit');
        self::assertSame('member.roles', $this->dig($this->json(), 'items', 0, 'action'));
        self::assertSame('ROLE_TREASURER', $this->dig($this->json(), 'items', 0, 'details', 'after'));
    }

    public function testRoleRulesPreventLockoutAndPrivilegeEscalation(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->loginAsPresident();

        $this->put("/api/admin/members/{$alice->getId()->toRfc4122()}/roles", ['roles' => ['admin']]);
        self::assertResponseStatusCodeSame(422);

        $this->put("/api/admin/members/{$this->president->getId()->toRfc4122()}/roles", ['roles' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testOnlyTheAdministratorTouchesThePresidency(): void
    {
        $alice = $this->createUser('alice@example.com');
        $other = $this->createUser('other@example.com');
        $other->grantRole(Role::President);
        $this->entityManager()->flush();
        $this->loginAsPresident();

        // Un compte de président volé ne nomme pas de complice et n'écarte pas l'autre président
        $this->put("/api/admin/members/{$alice->getId()->toRfc4122()}/roles", ['roles' => ['president']]);
        self::assertResponseStatusCodeSame(422);
        $this->put("/api/admin/members/{$other->getId()->toRfc4122()}/roles", ['roles' => ['secretaire']]);
        self::assertResponseStatusCodeSame(422);
        $this->postJson("/api/admin/members/{$other->getId()->toRfc4122()}/suspend", ['reason' => 'Coup de force']);
        self::assertResponseStatusCodeSame(422);

        // Ses propres rôles du bureau restent à sa main, la présidence en plus
        $this->put("/api/admin/members/{$this->president->getId()->toRfc4122()}/roles", ['roles' => ['president', 'secretaire']]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testTheAdministratorAppointsAndSuspendsPresidents(): void
    {
        $alice = $this->createUser('alice@example.com');
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('admin@example.com', $this->enableTwoFactor($admin));

        $this->put("/api/admin/members/{$alice->getId()->toRfc4122()}/roles", ['roles' => ['president']]);
        self::assertResponseStatusCodeSame(204);
        $this->postJson("/api/admin/members/{$alice->getId()->toRfc4122()}/suspend", ['reason' => 'Mandat contesté']);
        self::assertResponseStatusCodeSame(204);
    }

    public function testSuspendedMemberIsLoggedOutAndHidden(): void
    {
        $alice = $this->createUser('alice@example.com');
        $id = $alice->getId()->toRfc4122();
        $this->loginAsPresident();

        $this->postJson("/api/admin/members/{$id}/suspend", ['reason' => '']);
        self::assertResponseStatusCodeSame(422);

        $this->postJson("/api/admin/members/{$id}/suspend", ['reason' => 'Propos injurieux']);
        self::assertResponseStatusCodeSame(204);

        // Plus visible dans l'annuaire, plus de connexion possible
        $this->client->request('GET', '/api/members?q=alice');
        self::assertSame([], $this->dig($this->json(), 'items'));

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('suspendu', $this->digString($this->json(), 'error'));
    }

    public function testOpenSessionIsCutWhenSuspended(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $alice->suspend('Test', $this->now());
        $this->entityManager()->flush();

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        self::assertSame('account_suspended', $this->dig($this->json(), 'code'));
    }

    public function testPresidentCannotSuspendHimself(): void
    {
        $this->loginAsPresident();

        $this->postJson("/api/admin/members/{$this->president->getId()->toRfc4122()}/suspend", ['reason' => 'Oups']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testBroadcastToSelectionOrEveryone(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $this->loginAsPresident();

        $this->postJson('/api/admin/broadcasts', [
            'label' => 'reunion',
            'title' => 'Réunion du bureau',
            'body' => 'Jeudi 20 h au local.',
            'userIds' => [$alice->getId()->toRfc4122(), 'not-a-member'],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->json()['recipientCount']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[Réunion] Réunion du bureau');

        $this->postJson('/api/admin/broadcasts', ['label' => 'annulation', 'title' => 'Tournoi annulé', 'body' => 'Désolé !']);
        self::assertSame(3, $this->json()['recipientCount'], 'Alice, Bob et le président');
        self::assertTrue($this->json()['toAllMembers']);

        $this->postJson('/api/admin/broadcasts', ['label' => 'spam', 'title' => 'x', 'body' => 'y']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/admin/broadcasts');
        self::assertCount(2, (array) $this->json()['items']);
    }

    public function testAnnouncementsArePublishedPinnedAndReadByMembers(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsPresident();

        $this->postJson('/api/admin/announcements', ['title' => 'Nouveau local', 'body' => 'Rendez-vous rue des Fléchettes.']);
        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(2); // Alice et le président

        $this->postJson('/api/admin/announcements', ['title' => 'Règlement intérieur', 'body' => 'À lire.', 'pinned' => true]);

        $this->postJson('/api/admin/announcements', ['title' => '', 'body' => 'x']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', '/api/announcements');
        self::assertSame('Règlement intérieur', $this->dig($this->json(), 'items', 0, 'title'), 'Épinglée en premier');
        self::assertSame('Nouveau local', $this->dig($this->json(), 'items', 1, 'title'));

        $this->postJson('/api/admin/announcements', ['title' => 'Pirate', 'body' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTreasurerCannotAccessPresidentInterface(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('treasurer@example.com', $this->enableTwoFactor($treasurer));

        $this->client->request('GET', '/api/admin/members');
        self::assertResponseStatusCodeSame(403);
    }

    private function loginAsPresident(): void
    {
        $this->president = $this->createUser('president@example.com');
        $this->president->grantRole(Role::President);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('president@example.com', $this->enableTwoFactor($this->president));
    }

    /** @param array<mixed> $payload */
    private function put(string $uri, array $payload): void
    {
        $this->client->request('PUT', $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
