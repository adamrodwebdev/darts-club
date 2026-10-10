<?php

namespace App\Tests\Functional;

use App\Enum\Role;
use App\Tests\ApiTestCase;

final class MessagingTest extends ApiTestCase
{
    public function testDirectConversationIsReusedAndNotifiesRecipient(): void
    {
        $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $this->loginAs('alice@example.com');

        $this->postJson('/api/conversations', ['recipientId' => $bob->getId()->toRfc4122(), 'body' => 'Partie samedi ?']);
        self::assertResponseStatusCodeSame(201);
        $first = $this->json()['id'];
        self::assertIsString($first);

        // Notification « message » envoyée à Bob (email activé par défaut)
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[Message] Message de alice');

        $this->postJson('/api/conversations', ['recipientId' => $bob->getId()->toRfc4122(), 'body' => 'Relance']);
        self::assertSame($first, $this->json()['id'], 'Un seul fil par paire d\'adhérents');

        $this->switchTo('bob@example.com');
        $this->client->request('GET', '/api/conversations');
        $items = $this->items();
        self::assertCount(1, $items);
        self::assertSame(2, $items[0]['unread']);
        $with = $items[0]['with'];
        self::assertIsArray($with);
        self::assertSame('alice', $with['displayName']);
        self::assertArrayNotHasKey('email', $with);

        $this->client->request('POST', "/api/conversations/{$first}/read", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->client->request('GET', '/api/conversations');
        self::assertSame(0, $this->items()[0]['unread']);
    }

    public function testMailboxConversationReachesEveryRoleHolderAndAdmin(): void
    {
        $this->createUser('member@example.com');
        $president = $this->createUser('president@example.com');
        $president->grantRole(Role::President);
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        // Bureau : 2FA obligatoire
        $presidentSecret = $this->enableTwoFactor($president);
        $treasurerSecret = $this->enableTwoFactor($treasurer);

        $this->loginAs('member@example.com');
        $this->postJson('/api/conversations', ['mailbox' => 'president', 'subject' => 'Cotisation', 'body' => 'Puis-je payer en deux fois ?']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'];
        self::assertIsString($id);

        // Président + admin (hiérarchie), pas le trésorier
        self::assertEmailCount(2);

        $this->switchTo('treasurer@example.com', $treasurerSecret);
        $this->client->request('GET', "/api/conversations/{$id}/messages");
        self::assertResponseStatusCodeSame(403);

        $this->switchTo('president@example.com', $presidentSecret);
        $this->client->request('GET', '/api/conversations');
        self::assertCount(1, $this->items());
        $this->postJson("/api/conversations/{$id}/messages", ['body' => 'Oui, bien sûr.']);
        self::assertResponseStatusCodeSame(201);
        self::assertTrue($this->json()['mine']);

        $this->switchTo('member@example.com');
        $this->client->request('GET', "/api/conversations/{$id}/messages");
        $items = $this->items();
        self::assertCount(2, $items);
        self::assertSame('Oui, bien sûr.', $items[1]['body']);
        self::assertFalse($items[1]['mine']);
    }

    public function testOutsiderCannotReadOrReply(): void
    {
        $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $this->createUser('mallory@example.com');

        $this->loginAs('alice@example.com');
        $this->postJson('/api/conversations', ['recipientId' => $bob->getId()->toRfc4122(), 'body' => 'Privé']);
        $id = $this->json()['id'];
        self::assertIsString($id);

        $this->switchTo('mallory@example.com');
        $this->client->request('GET', "/api/conversations/{$id}/messages");
        self::assertResponseStatusCodeSame(403);
        $this->postJson("/api/conversations/{$id}/messages", ['body' => 'Intrus']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidTargetsAreRejected(): void
    {
        $alice = $this->createUser('alice@example.com');
        $pending = $this->createUser('pending@example.com', verified: false);
        $this->loginAs('alice@example.com');

        foreach ([
            ['recipientId' => $alice->getId()->toRfc4122(), 'body' => 'Moi'],
            ['recipientId' => $pending->getId()->toRfc4122(), 'body' => 'Non vérifié'],
            ['mailbox' => 'president', 'recipientId' => $alice->getId()->toRfc4122(), 'body' => 'Les deux'],
            ['mailbox' => 'roi', 'body' => 'Boîte inconnue'],
            ['mailbox' => 'president', 'body' => "   \n  "],
        ] as $payload) {
            $this->postJson('/api/conversations', $payload);
            self::assertResponseStatusCodeSame(422, json_encode($payload, \JSON_THROW_ON_ERROR));
        }
    }

    public function testMemberDirectoryHidesEmails(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $this->createUser('pending@example.com', verified: false);
        $this->loginAs('alice@example.com');

        $this->client->request('GET', '/api/members?q=bo');
        $items = $this->items();
        self::assertCount(1, $items);
        self::assertSame(['id', 'displayName', 'avatarUrl'], array_keys($items[0]));
    }

    public function testDeletedAuthorIsAnonymized(): void
    {
        $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $this->loginAs('alice@example.com');
        $this->postJson('/api/conversations', ['recipientId' => $bob->getId()->toRfc4122(), 'body' => 'Au revoir']);
        $id = $this->json()['id'];
        self::assertIsString($id);

        $this->client->request('DELETE', '/api/me', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode(['confirmation' => 'SUPPRIMER', 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(204);

        $this->loginAs('bob@example.com');
        $this->client->request('GET', "/api/conversations/{$id}/messages");
        self::assertResponseIsSuccessful();
        $items = $this->items();
        $author = $items[0]['author'];
        self::assertIsArray($author);
        self::assertSame('Ancien adhérent', $author['displayName']);
    }

    private function loginAs(string $email): void
    {
        $this->login($email, self::PASSWORD);
        self::assertResponseIsSuccessful();
    }

    private function switchTo(string $email, ?string $totpSecret = null): void
    {
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        if (null !== $totpSecret) {
            $this->loginWithTwoFactor($email, $totpSecret);
        } else {
            $this->loginAs($email);
        }
    }

    /** @return list<array<mixed>> */
    private function items(): array
    {
        $items = $this->json()['items'] ?? null;
        self::assertIsArray($items);

        $list = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $list[] = $item;
        }

        return $list;
    }
}
