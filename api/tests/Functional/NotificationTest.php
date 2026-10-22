<?php

namespace App\Tests\Functional;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Push\PushEndpoint;
use App\Notification\Recipients;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakePushSender;
use App\Tests\Double\MercureRecorder;

final class NotificationTest extends ApiTestCase
{
    public function testBroadcastReachesEveryVerifiedMemberOnAllChannels(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $this->createUser('pending@example.com', verified: false);
        $this->subscribePush($alice, 'https://push.example/alice');

        $this->notifier()->notify(
            new NotificationContent(Category::Event, 'Tournoi d\'automne', 'Rendez-vous samedi 14 h.', '/evenements/1'),
            Recipients::allMembers(),
        );

        // Email : 2 adhérents vérifiés, sujet labellisé
        self::assertEmailCount(2);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[Événement] Tournoi d\'automne');

        // Push : seul l'abonnement d'Alice
        $sent = $this->service(FakePushSender::class, FakePushSender::class)->sent;
        self::assertCount(1, $sent);
        self::assertStringContainsString('/evenements/1', $sent[0]['payload']);

        // Temps réel : 2 publications privées
        $published = $this->service(MercureRecorder::class, MercureRecorder::class)->published;
        self::assertCount(2, $published);
        self::assertTrue($published[0]['private']);
        self::assertStringEndsWith('/notifications', $published[0]['topics'][0]);

        // Centre de notifications
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', '/api/notifications');
        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame(1, $data['unread']);
        self::assertIsArray($data['items']);
        self::assertCount(1, $data['items']);
    }

    public function testPreferencesDisableEmailButKeepInApp(): void
    {
        $user = $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->putJson('/api/me/notification-preferences', ['preferences' => ['evenement' => ['push']]]);
        self::assertResponseIsSuccessful();
        $preferences = $this->json()['preferences'];
        self::assertIsArray($preferences);
        self::assertSame(['push'], $preferences['evenement']);

        $this->notifier()->notify(
            new NotificationContent(Category::Event, 'Tournoi', 'Samedi.'),
            Recipients::users($user->getId()),
        );

        self::assertEmailCount(0);
        $this->client->request('GET', '/api/notifications?unread=1');
        self::assertSame(1, $this->json()['unread']);
    }

    public function testInAppChannelCannotBeDisabled(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->putJson('/api/me/notification-preferences', ['preferences' => ['evenement' => ['in_app']]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMemberCanOnlyReadOwnNotifications(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->createUser('mallory@example.com');
        $this->notifier()->notify(new NotificationContent(Category::Message, 'Salut', 'Privé'), Recipients::users($alice->getId()));

        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('GET', '/api/notifications');
        $items = $this->json()['items'];
        self::assertIsArray($items);
        self::assertIsArray($items[0]);
        $id = $items[0]['id'];
        self::assertIsString($id);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('mallory@example.com', self::PASSWORD);
        $this->client->request('POST', "/api/notifications/{$id}/read", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);
        $this->client->request('POST', "/api/notifications/{$id}/read", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/notifications');
        self::assertSame(0, $this->json()['unread']);
    }

    public function testExpiredPushSubscriptionIsRemoved(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->subscribePush($alice, 'https://push.example/gone');
        $this->service(FakePushSender::class, FakePushSender::class)->expiredEndpoints = ['https://push.example/gone'];

        $this->notifier()->notify(new NotificationContent(Category::Meeting, 'AG', 'Mardi.'), Recipients::users($alice->getId()));

        self::assertSame(0, $this->entityManager()->getRepository(PushSubscription::class)->count([]));
    }

    public function testPushSubscriptionEndpoints(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/push/public-key');
        self::assertSame('test-public-key', $this->json()['publicKey']);

        $subscription = ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret']];
        $this->postJson('/api/push/subscriptions', $subscription);
        self::assertResponseStatusCodeSame(201);

        $this->postJson('/api/push/subscriptions', ['endpoint' => 'http://insecure.example', 'keys' => ['p256dh' => 'k', 'auth' => 'a']]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('DELETE', '/api/push/subscriptions', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($subscription, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(204);
    }

    public function testRealtimeAuthorizationSetsHttpOnlyCookie(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->client->request('POST', '/api/realtime/authorization', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/.well-known/mercure', $this->json()['hub']);

        $cookies = $this->client->getResponse()->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertTrue($cookies[0]->isHttpOnly());
    }

    public function testNotificationContentRejectsExternalLinks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NotificationContent(Category::Event, 'Titre', 'Corps', '//evil.example');
    }

    private function notifier(): MemberNotifier
    {
        return $this->service(MemberNotifier::class, MemberNotifier::class);
    }

    private function subscribePush(User $user, string $endpoint): void
    {
        $em = $this->entityManager();
        $em->persist(new PushSubscription($user, new PushEndpoint($endpoint, 'key', 'auth'), $this->now()));
        $em->flush();
    }

    /** @param array<mixed> $payload */
    private function putJson(string $uri, array $payload): void
    {
        $this->client->request('PUT', $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
