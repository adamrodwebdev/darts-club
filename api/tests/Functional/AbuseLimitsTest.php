<?php

namespace App\Tests\Functional;

use App\Entity\PushSubscription;
use App\Notification\Push\PushEndpoint;
use App\Notification\Push\PushSubscriptions;
use App\Tests\ApiTestCase;

/** Revue d'octobre 2026 : limites contre les abus (débit, emails, push, sessions). */
final class AbuseLimitsTest extends ApiTestCase
{
    public function testEveryAddressOfAnIpv6NetworkSharesTheLoginLimit(): void
    {
        $this->createUser('alice@example.com');

        // Cinq mauvais mots de passe, chacun depuis une autre adresse du même /64
        for ($host = 1; $host <= 5; ++$host) {
            $this->client->setServerParameter('REMOTE_ADDR', '2001:db8:7:9::'.$host);
            $this->login('alice@example.com', 'mauvais-mot-de-passe');
            self::assertResponseStatusCodeSame(401);
        }

        $this->client->setServerParameter('REMOTE_ADDR', '2001:db8:7:9:abcd::1');
        $this->login('alice@example.com', self::PASSWORD);
        self::assertSame('too_many_attempts', $this->json()['code']);

        // Un autre réseau n'est pas concerné
        $this->client->setServerParameter('REMOTE_ADDR', '2001:db8:7:a::1');
        $this->login('alice@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
    }

    public function testCurrentPasswordGuessesCountBeforeTheCheck(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->postJson('/api/me/password', ['currentPassword' => 'essai-'.$attempt, 'newPassword' => 'Nouveau-Mot-De-Passe-2026!']);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('currentPassword', $this->dig($this->json(), 'violations', 0, 'propertyPath'));
        }

        // Même le bon mot de passe attend la fin de la limite
        $this->postJson('/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'Nouveau-Mot-De-Passe-2026!']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testVisitorEmailsToOneAddressAreCappedPerDay(): void
    {
        $this->createUser('alice@example.com');

        // Inscriptions répétées à l'adresse d'Alice, depuis des origines toujours nouvelles
        $sent = 0;
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $this->client->setServerParameter('REMOTE_ADDR', '198.51.100.'.$attempt);
            $this->postJson('/api/auth/register', ['email' => 'alice@example.com', 'password' => 'Autre-Mot-De-Passe-2026!']);
            self::assertResponseStatusCodeSame(202);
            $sent += \count(self::getMailerMessages());
        }

        self::assertSame(4, $sent);
    }

    public function testOnlyBrowserPushServicesAreAccepted(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        foreach (['https://attaquant.example/push', 'https://fcm.googleapis.com:8443/fcm/send/x', 'https://fcm.googleapis.com.attaquant.example/x'] as $endpoint) {
            $this->postJson('/api/push/subscriptions', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'key', 'auth' => 'secret']]);
            self::assertResponseStatusCodeSame(422, $endpoint);
        }

        $this->postJson('/api/push/subscriptions', ['endpoint' => 'https://wns2-par02p.notify.windows.com/w/?token=abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret']]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testAMemberKeepsTenPushSubscriptionsTheOldestMakingRoom(): void
    {
        $alice = $this->createUser('alice@example.com');
        $em = $this->entityManager();
        for ($device = 1; $device <= PushSubscriptions::MAX_PER_USER; ++$device) {
            $endpoint = new PushEndpoint('https://fcm.googleapis.com/fcm/send/appareil-'.$device, 'key', 'auth');
            $em->persist(new PushSubscription($alice, $endpoint, $this->now()->modify('-'.(20 - $device).' days')));
        }
        $em->flush();

        $this->login('alice@example.com', self::PASSWORD);
        $this->postJson('/api/push/subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/nouveau', 'keys' => ['p256dh' => 'key', 'auth' => 'secret']]);
        self::assertResponseStatusCodeSame(201);

        $endpoints = array_map(
            static fn (PushSubscription $subscription): string => $subscription->endpoint()->url,
            $em->getRepository(PushSubscription::class)->findAll(),
        );
        self::assertCount(PushSubscriptions::MAX_PER_USER, $endpoints);
        self::assertNotContains('https://fcm.googleapis.com/fcm/send/appareil-1', $endpoints);
        self::assertContains('https://fcm.googleapis.com/fcm/send/nouveau', $endpoints);
    }

    public function testRequestBodiesAreReadAsJsonOnly(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            ['email' => 'bob@example.com', 'password' => 'Autre-Mot-De-Passe-2026!'],
            server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN],
        );

        self::assertResponseStatusCodeSame(415);
    }

    public function testAnAnonymousCallToAProtectedRouteOpensNoSession(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }
}
