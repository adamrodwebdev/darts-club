<?php

namespace App\Tests\Functional;

use App\ClubEvent\EventAudience;
use App\ClubEvent\EventDetails;
use App\Entity\ClubEvent;
use App\Enum\Role;
use App\Repository\EventViewRepository;
use App\Tests\ApiTestCase;
use Symfony\Component\Clock\MockClock;

/** Horloge figée au 15/10/2026 12:00 UTC (config/services_test.yaml). */
final class EventAudienceTest extends ApiTestCase
{
    private const BROWSER_A = 'Mozilla/5.0 (Android 14) Firefox/131.0';
    private const BROWSER_B = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) Safari/604.1';

    public function testUniquePeopleAreCountedAndOnlyTheAdministratorSeesThem(): void
    {
        $event = $this->publicEvent();
        $id = $event->getId()->toRfc4122();
        $slug = (string) $event->getSlug();

        // Visiteurs anonymes : une personne = une empreinte, quel que soit le nombre de vues
        $this->publicView($slug, self::BROWSER_A);
        $this->publicView($slug, self::BROWSER_A);
        $this->publicView($slug, self::BROWSER_B);
        $this->publicView($slug, 'Mozilla/5.0 (compatible; Googlebot/2.1)');
        $this->publicView($slug, self::BROWSER_A, '203.0.113.7');
        self::assertResponseStatusCodeSame(204);

        // Adhérente : comptée une fois, sur la page adhérent comme sur la page publique
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);
        $this->postJson("/api/events/{$id}/view", []);
        self::assertResponseStatusCodeSame(204);
        $this->postJson("/api/events/{$id}/view", []);
        $this->publicView($slug, self::BROWSER_A);
        $this->client->request('GET', "/api/admin/events/{$id}/audience");
        self::assertResponseStatusCodeSame(403, 'Statistiques réservées à l\'administrateur');

        // Organisateur : ses propres vues ne comptent pas
        $this->loginWithRole('secretary@example.com', Role::Secretary);
        $this->postJson("/api/events/{$id}/view", []);

        $this->loginWithRole('president@example.com', Role::President);
        $this->client->request('GET', "/api/admin/events/{$id}/audience");
        self::assertResponseStatusCodeSame(403, 'La présidence n\'y a pas accès');

        $this->loginWithRole('admin@example.com', Role::Admin);
        $this->client->request('GET', "/api/admin/events/{$id}/audience");
        self::assertResponseIsSuccessful();
        $report = $this->json();
        self::assertSame([4, 1, 3], [$report['unique'], $report['members'], $report['visitors']]);
        self::assertSame([['day' => '2026-10-15', 'members' => 1, 'visitors' => 3]], $report['days']);

        $this->client->request('GET', "/api/admin/events/{$id}/audience.csv");
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('2026-10-15;1;3', (string) $this->client->getResponse()->getContent());

        // Aucun identifiant en clair : seulement des empreintes
        $views = $this->service(EventViewRepository::class, EventViewRepository::class)->findAll();
        foreach ($views as $view) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $view->visitorKey());
        }
    }

    public function testMeasurementEndsAndDetailIsSummarized(): void
    {
        $event = $this->publicEvent();
        $slug = (string) $event->getSlug();
        $this->publicView($slug, self::BROWSER_A);
        $audience = $this->service(EventAudience::class, EventAudience::class);
        $clock = $this->service('clock', MockClock::class);

        // 30 jours après l'événement : clé détruite, plus aucune vue enregistrée
        $clock->modify('+40 days');
        $audience->purge();
        $this->entityManager()->refresh($event);
        self::assertNull($event->audienceKey($clock->now()));
        $audience->record($event, null, false, '198.51.100.20', self::BROWSER_B);
        self::assertSame(1, $audience->report($event)['unique']);

        // 13 mois après : le détail est remplacé par les totaux
        $clock->modify('+14 months');
        $audience->purge();
        $this->entityManager()->refresh($event);
        self::assertSame([], $this->service(EventViewRepository::class, EventViewRepository::class)->findAll());
        $report = $audience->report($event);
        self::assertSame([1, 0, 1, true], [$report['unique'], $report['members'], $report['visitors'], $report['archived']]);
    }

    private function publicEvent(): ClubEvent
    {
        $organizer = $this->createUser('organizer@example.com');
        $startsAt = new \DateTimeImmutable('2026-10-24 20:00 Europe/Paris');
        $event = ClubEvent::schedule(
            $organizer,
            new EventDetails('Soirée cricket', 'Initiation', 'Bar du Centre, Louhans', $startsAt, $startsAt->modify('+4 hours'), null, true, 'Louhans'),
            new \DateTimeImmutable('2026-10-15 12:00 UTC'),
        );
        $event->releaseEvents();
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();

        return $event;
    }

    private function publicView(string $slug, string $userAgent, string $ip = '198.51.100.10'): void
    {
        $this->client->request('POST', "/api/public/events/{$slug}/view", server: [
            'HTTP_ORIGIN' => self::FRONT_ORIGIN,
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => $ip,
        ]);
    }

    private function loginWithRole(string $email, Role $role): void
    {
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $user = $this->createUser($email);
        $user->grantRole($role);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor($email, $this->enableTwoFactor($user));
    }
}
