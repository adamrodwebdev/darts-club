<?php

namespace App\Tests\Functional;

use App\ClubEvent\EventDetails;
use App\ClubEvent\EventReminders;
use App\Entity\ClubEvent;
use App\Entity\User;
use App\Enum\Role;
use App\Tests\ApiTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Horloge figée au 15/10/2026 12:00 UTC (config/services_test.yaml). */
final class ClubEventTest extends ApiTestCase
{
    private User $secretary;

    public function testSecretarySchedulesEventAndMembersAreNotified(): void
    {
        $this->createUser('alice@example.com');
        $this->loginAsSecretary();

        $this->postJson('/api/events', self::payload());
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Tournoi d\'automne', $this->json()['title']);
        self::assertSame('2026-2027', $this->json()['season']);

        self::assertEmailCount(2);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[Événement] Nouvel événement : Tournoi d\'automne');
        self::assertEmailTextBodyContains($email, 'Samedi 24 octobre à 14:00');

        $this->postJson('/api/events', self::payload(['endsAt' => '2026-10-24T13:00:00+02:00']));
        self::assertResponseStatusCodeSame(422);
    }

    public function testMembersCannotOrganize(): void
    {
        $this->createUser('alice@example.com');
        $this->login('alice@example.com', self::PASSWORD);

        $this->postJson('/api/events', self::payload());
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegistrationRespectsCapacity(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $id = $this->createEventAsSecretary(['capacity' => 1]);

        $this->switchTo('alice@example.com');
        $this->postJson("/api/events/{$id}/registration", []);
        self::assertResponseStatusCodeSame(204);
        $this->postJson("/api/events/{$id}/registration", []);
        self::assertResponseStatusCodeSame(422, 'Déjà inscrite');

        $this->switchTo('bob@example.com');
        $this->postJson("/api/events/{$id}/registration", []);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Complet', $this->digString($this->json(), 'detail'));

        $this->switchTo('alice@example.com');
        $this->client->request('DELETE', "/api/events/{$id}/registration", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);

        $this->switchTo('bob@example.com');
        $this->postJson("/api/events/{$id}/registration", []);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/events/{$id}");
        self::assertTrue($this->json()['isRegistered']);
        self::assertNull($this->json()['participants'], 'Liste des inscrits réservée aux organisateurs');
    }

    public function testRescheduleNotifiesParticipantsAndCancelNotifiesEveryone(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $id = $this->createEventAsSecretary();

        $this->switchTo('alice@example.com');
        $this->postJson("/api/events/{$id}/registration", []);

        $this->loginAsSecretaryAgain();
        $this->client->request('PUT', "/api/events/{$id}", server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode(self::payload(['startsAt' => '2026-10-25T14:00:00+01:00', 'endsAt' => '2026-10-25T20:00:00+01:00']), \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertEmailCount(1, message: 'Seule Alice, inscrite');

        $this->postJson("/api/events/{$id}/cancel", ['reason' => 'Salle indisponible']);
        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(3);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, '[Annulation] Annulé : Tournoi d\'automne');
        self::assertEmailTextBodyContains($email, 'Salle indisponible');
    }

    public function testRemindersAreSentOnceAtDayMinusSevenAndDayMinusOne(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $id = $this->createEventAsSecretary(); // 24/10, J-9
        $this->switchTo('alice@example.com');
        $this->postJson("/api/events/{$id}/registration", []);

        $clock = $this->service('clock', MockClock::class);
        $reminders = $this->service(EventReminders::class, EventReminders::class);

        self::assertSame(0, $reminders->sendDue(), 'J-9 : rien');

        $clock->modify('2026-10-17 15:00:00');
        self::assertSame(1, $reminders->sendDue(), 'J-7 : tous les adhérents');
        self::assertSame(0, $reminders->sendDue(), 'Pas de doublon');

        $clock->modify('2026-10-23 13:00:00');
        self::assertSame(1, $reminders->sendDue(), 'J-1 : les inscrits');
        self::assertSame(0, $reminders->sendDue());
    }

    public function testIcsExportAndPersonalCalendarFeed(): void
    {
        $id = $this->createEventAsSecretary();

        $this->client->request('GET', "/api/events/{$id}/calendar.ics");
        self::assertResponseIsSuccessful();
        $ics = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        self::assertStringContainsString('DTSTART:20261024T120000Z', $ics);
        self::assertStringContainsString('SUMMARY:Tournoi d\'automne', $ics);
        self::assertStringContainsString('LOCATION:Bar Le 180\, Lyon', $ics, 'Virgule échappée');

        $this->postJson('/api/me/calendar-feed', []);
        self::assertResponseStatusCodeSame(201);
        $url = $this->digString($this->json(), 'url');
        $path = (string) parse_url($url, \PHP_URL_PATH);

        // Sans session : le jeton suffit
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('X-WR-CALNAME:Darts Club', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/calendar/'.str_repeat('a', 48).'.ics');
        self::assertResponseStatusCodeSame(404);
    }

    public function testArchiveAcceptsPhotosAndPostsOnceStarted(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $past = $this->pastEvent();
        $future = $this->createEventAsSecretary();
        $pastId = $past->getId()->toRfc4122();

        $this->switchTo('alice@example.com');
        $this->uploadPhotos($future, 1);
        self::assertResponseStatusCodeSame(422, 'Pas de photos avant l\'événement');

        $this->uploadPhotos($pastId, 2);
        self::assertResponseStatusCodeSame(201);
        $photoId = $this->digString($this->json(), 'items', 0, 'id');

        $this->postJson("/api/events/{$pastId}/posts", ['body' => 'Quelle soirée ! 180 au 3e tour.']);
        self::assertResponseStatusCodeSame(201);
        $postId = $this->digString($this->json(), 'id');

        $this->client->request('GET', "/api/event-photos/{$photoId}/thumb");
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        // Bob ne peut pas supprimer le contenu d'Alice
        $this->switchTo('bob@example.com');
        $this->client->request('DELETE', "/api/event-posts/{$postId}", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', "/api/events/{$pastId}");
        self::assertCount(2, (array) $this->json()['photos']);
        self::assertSame('alice', $this->dig($this->json(), 'posts', 0, 'author', 'displayName'));
        self::assertFalse($this->dig($this->json(), 'posts', 0, 'mine'));

        // La secrétaire modère
        $this->loginAsSecretaryAgain();
        $this->client->request('DELETE', "/api/event-photos/{$photoId}", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        unset($alice);
    }

    public function testArchivesAreFilteredBySeasonAndSearch(): void
    {
        $this->createUser('alice@example.com');
        $this->pastEvent('Soirée de rentrée', '2026-09-12 19:00');
        $this->pastEvent('Finale régionale', '2026-05-10 14:00');
        $this->login('alice@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/events?period=past&season=2026-2027');
        self::assertSame(['Soirée de rentrée'], array_column((array) $this->json()['items'], 'title'));

        $this->client->request('GET', '/api/events?period=past&q=finale');
        self::assertSame(['Finale régionale'], array_column((array) $this->json()['items'], 'title'));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Tournoi d\'automne',
            'description' => "Format 501 double out.\nInscriptions sur place.",
            'location' => 'Bar Le 180, Lyon',
            'startsAt' => '2026-10-24T14:00:00+02:00',
            'endsAt' => '2026-10-24T20:00:00+02:00',
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function createEventAsSecretary(array $overrides = []): string
    {
        $this->loginAsSecretary();
        $this->postJson('/api/events', self::payload($overrides));
        self::assertResponseStatusCodeSame(201);

        return $this->digString($this->json(), 'id');
    }

    private function pastEvent(string $title = 'Soirée passée', string $start = '2026-10-10 19:00'): ClubEvent
    {
        $organizer = $this->createUser('organizer-'.bin2hex(random_bytes(3)).'@example.com');
        $startsAt = new \DateTimeImmutable($start.' Europe/Paris');
        $event = ClubEvent::schedule(
            $organizer,
            new EventDetails($title, 'Archive', 'Club', $startsAt, $startsAt->modify('+4 hours')),
            $startsAt->modify('-1 month'),
        );
        $event->releaseEvents();
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();

        return $event;
    }

    private function uploadPhotos(string $eventId, int $count): void
    {
        $files = [];
        for ($i = 0; $i < $count; ++$i) {
            $copy = tempnam(sys_get_temp_dir(), 'photo');
            self::assertIsString($copy);
            copy(__DIR__.'/../Fixtures/avatar.jpg', $copy);
            $files[] = new UploadedFile($copy, "photo{$i}.jpg", test: true);
        }

        $this->client->request('POST', "/api/events/{$eventId}/photos", files: ['photos' => $files], server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
    }

    private string $secretarySecret = '';

    private function loginAsSecretary(): void
    {
        $this->secretary = $this->createUser('secretary@example.com');
        $this->secretary->grantRole(Role::Secretary);
        $this->entityManager()->flush();
        $this->secretarySecret = $this->enableTwoFactor($this->secretary);
        $this->loginWithTwoFactor('secretary@example.com', $this->secretarySecret);
    }

    private function loginAsSecretaryAgain(): void
    {
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->loginWithTwoFactor('secretary@example.com', $this->secretarySecret);
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login($email, self::PASSWORD);
        self::assertResponseIsSuccessful();
    }
}
