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
        // Typographie française : espace insécable avant les deux-points
        self::assertEmailSubjectContains($email, "[Événement] Nouvel événement\u{00A0}: Tournoi d'automne");
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
        self::assertResponseStatusCodeSame(204, 'Même réponse : sans effet');

        $this->switchTo('bob@example.com');
        $this->postJson("/api/events/{$id}/registration", []);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Complet', $this->digString($this->json(), 'detail'));
        // « Pas sûr » ne prend pas de place
        $this->postJson("/api/events/{$id}/registration", ['answer' => 'pas-sur']);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/events/{$id}");
        self::assertSame('pas-sur', $this->json()['myAnswer']);
        self::assertSame(1, $this->json()['registered']);
        self::assertSame(1, $this->json()['maybe']);
        $this->postJson("/api/events/{$id}/registration", ['answer' => 'peut-etre']);
        self::assertResponseStatusCodeSame(422);

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
        self::assertEmailSubjectContains($email, "[Annulation] Annulé\u{00A0}: Tournoi d'automne");
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
        self::assertStringContainsString('X-WR-CALNAME:Skull Darts 71', (string) $this->client->getResponse()->getContent());

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

        // La secrétaire modère : inscrit au journal, contrairement au retrait par l'auteur
        $this->loginAsSecretaryAgain();
        $this->client->request('DELETE', "/api/event-photos/{$photoId}", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([['photo.delete', 'Soirée passée', ['author' => 'alice']]], array_map(
            static fn (array $entry): array => [$entry['action'], $entry['target'], $entry['details']],
            $this->auditTrail(),
        ));
        unset($alice);
    }

    public function testARefusedPhotoBatchLeavesNoFileBehind(): void
    {
        $this->createUser('alice@example.com');
        $pastId = $this->pastEvent()->getId()->toRfc4122();
        $this->switchTo('alice@example.com');
        $before = $this->storedPhotoFiles();

        // Une vraie photo, puis un fichier qui n'est pas une image : l'envoi entier est refusé
        $photo = tempnam(sys_get_temp_dir(), 'photo');
        $text = tempnam(sys_get_temp_dir(), 'text');
        self::assertIsString($photo);
        self::assertIsString($text);
        copy(__DIR__.'/../Fixtures/avatar.jpg', $photo);
        file_put_contents($text, 'pas une image');
        $this->client->request('POST', "/api/events/{$pastId}/photos", files: ['photos' => [
            new UploadedFile($photo, 'soiree.jpg', test: true),
            new UploadedFile($text, 'notes.jpg', test: true),
        ]], server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->storedPhotoFiles());
    }

    public function testOrganizersAttachAPosterToAnUpcomingEvent(): void
    {
        $this->createUser('alice@example.com');
        $id = $this->createEventAsSecretary();

        $this->uploadPoster($id);
        self::assertResponseStatusCodeSame(201);
        $first = $this->json();
        self::assertGreaterThan(0, $first['width']);
        $cardUrl = $this->digString($first, 'cardUrl');

        $this->client->request('GET', '/api/events');
        self::assertSame($cardUrl, $this->dig($this->json(), 'items', 0, 'poster', 'cardUrl'));

        $this->client->request('GET', $cardUrl);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertStringContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', "/api/events/{$id}/poster/".str_repeat('a', 32));
        self::assertResponseStatusCodeSame(404, 'Fichier d\'un autre événement ou inexistant');

        // Nouvelle affiche : l'ancienne disparaît
        $this->uploadPoster($id);
        self::assertResponseStatusCodeSame(201);
        $this->client->request('GET', $cardUrl);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', "/api/events/{$id}/poster", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(422, 'Aucun fichier');

        $this->switchTo('alice@example.com');
        $this->client->request('GET', "/api/events/{$id}");
        self::assertNotNull($this->json()['poster']);
        $this->uploadPoster($id);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('DELETE', "/api/events/{$id}/poster", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(403);

        $this->loginAsSecretaryAgain();
        $this->client->request('DELETE', "/api/events/{$id}/poster", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/events/{$id}");
        self::assertNull($this->json()['poster']);

        $this->postJson("/api/events/{$id}/cancel", ['reason' => 'Salle indisponible']);
        $this->uploadPoster($id);
        self::assertResponseStatusCodeSame(422, 'Pas d\'affiche sur un événement annulé');
    }

    public function testPeopleWithoutAccountAnswerPublicEventsByEmail(): void
    {
        $this->loginAsSecretary();
        $this->postJson('/api/events', self::payload(['public' => true]));
        self::assertResponseStatusCodeSame(422, 'Ville obligatoire pour un événement public');

        $this->postJson('/api/events', self::payload(['public' => true, 'city' => 'Mâcon', 'capacity' => 1]));
        self::assertResponseStatusCodeSame(201);
        $id = $this->digString($this->json(), 'id');
        $path = $this->digString($this->json(), 'publicPath');
        self::assertMatchesRegularExpression('#^/e/tournoi-d-automne-2026-10-24-[a-f0-9]{6}$#', $path);
        $slug = substr($path, 3);
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);

        // Page publique : ville seulement, aucun participant nommé
        $this->client->request('GET', "/api/public/events/{$slug}");
        self::assertResponseIsSuccessful();
        self::assertSame('Mâcon', $this->json()['city']);
        self::assertArrayNotHasKey('location', $this->json());
        self::assertSame(0, $this->json()['going']);
        $this->client->request('GET', '/api/events');
        self::assertResponseStatusCodeSame(401, 'Le calendrier reste réservé');

        $this->guestAnswer($slug, ['consent' => false]);
        self::assertResponseStatusCodeSame(422);
        $this->guestAnswer($slug, ['website' => 'http://spam.example', 'email' => 'bot@example.net']);
        self::assertResponseStatusCodeSame(202, 'Piège à robots : même réponse');
        self::assertNull($this->linkSentTo('bot@example.net'));

        $this->guestAnswer($slug, ['name' => 'Zoé', 'email' => 'Zoe@Example.net']);
        self::assertResponseStatusCodeSame(202);
        $zoe = $this->linkSentTo('zoe@example.net');
        self::assertNotNull($zoe);

        $this->client->request('GET', "/api/public/events/{$slug}");
        self::assertSame(0, $this->json()['going'], 'Non confirmée : ne compte pas');
        $this->tokenAction('lookup', $zoe);
        self::assertFalse($this->json()['confirmed']);
        self::assertNull($this->dig($this->json(), 'event', 'location'));

        $this->tokenAction('confirm', $zoe);
        self::assertResponseIsSuccessful();
        self::assertSame('je-viens', $this->json()['answer']);
        self::assertSame('Bar Le 180, Lyon', $this->dig($this->json(), 'event', 'location'), 'Adresse donnée après confirmation');
        $this->client->request('GET', "/api/public/events/{$slug}");
        self::assertSame(1, $this->json()['going']);
        self::assertTrue($this->json()['full']);

        // Complet : un second invité ne peut que répondre « pas sûr »
        $this->guestAnswer($slug, ['name' => 'Max', 'email' => 'max@example.net']);
        $max = $this->linkSentTo('max@example.net');
        self::assertNotNull($max);
        $this->tokenAction('confirm', $max);
        self::assertResponseStatusCodeSame(422);
        $this->guestAnswer($slug, ['name' => 'Max', 'email' => 'max@example.net', 'answer' => 'pas-sur']);
        $newMax = $this->linkSentTo('max@example.net');
        self::assertNotNull($newMax);
        self::assertNotSame($max, $newMax, 'Nouvelle demande : nouveau lien');
        $this->tokenAction('lookup', $max);
        self::assertResponseStatusCodeSame(404, 'L\'ancien lien ne vaut plus');
        $this->tokenAction('confirm', $newMax);
        self::assertSame('pas-sur', $this->json()['answer']);

        $this->tokenAction('answer', $zoe, ['answer' => 'pas-sur']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', "/api/public/events/{$slug}");
        self::assertSame([0, 2], [$this->json()['going'], $this->json()['maybe']]);

        // Les organisateurs voient les invités ; les invités confirmés apprennent l'annulation
        $this->loginAsSecretaryAgain();
        $this->client->request('GET', "/api/events/{$id}");
        self::assertSame(['Zoé', 'Max'], array_column((array) $this->json()['guests'], 'name'));
        $this->postJson("/api/events/{$id}/cancel", ['reason' => 'Salle indisponible']);
        self::assertResponseStatusCodeSame(204);
        $cancelled = array_filter(self::getMailerMessages(), static fn ($m): bool => $m instanceof \Symfony\Component\Mime\Email && 'max@example.net' === ($m->getTo()[0] ?? null)?->getAddress() && str_contains((string) $m->getSubject(), 'Annulé'));
        self::assertCount(1, $cancelled);

        $this->tokenAction('cancel', $zoe);
        self::assertResponseStatusCodeSame(204, 'Se retirer reste possible (données effacées)');
        $this->tokenAction('lookup', 'x');
        self::assertResponseStatusCodeSame(404);
    }

    public function testGuestNamesWaitForConfirmationAndOrganizersRemoveFakeGuests(): void
    {
        $this->loginAsSecretary();
        $this->postJson('/api/events', self::payload(['public' => true, 'city' => 'Mâcon']));
        $id = $this->digString($this->json(), 'id');
        $slug = substr($this->digString($this->json(), 'publicPath'), 3);
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);

        $this->guestAnswer($slug, ['name' => 'Zoé', 'email' => 'zoe@example.net']);
        $this->tokenAction('confirm', (string) $this->linkSentTo('zoe@example.net'));
        self::assertResponseIsSuccessful();

        // Quelqu'un qui connaît son adresse renvoie le formulaire : sans l'email, il ne la renomme pas
        $this->guestAnswer($slug, ['name' => 'Usurpateur', 'email' => 'zoe@example.net']);
        self::assertResponseStatusCodeSame(202);

        // Adresses jetables : au-delà du plafond du jour (3 en test, 30 en production), plus de confirmation
        foreach (['bot-a', 'bot-b'] as $bot) {
            $this->guestAnswer($slug, ['name' => 'Robot', 'email' => "{$bot}@example.net"]);
            $this->tokenAction('confirm', (string) $this->linkSentTo("{$bot}@example.net"));
            self::assertResponseIsSuccessful();
        }
        $this->guestAnswer($slug, ['name' => 'Robot', 'email' => 'bot-c@example.net']);
        $this->tokenAction('confirm', (string) $this->linkSentTo('bot-c@example.net'));
        self::assertResponseStatusCodeSame(429);

        // L'organisatrice voit Zoé sous son nom et retire un faux invité (inscrit au journal)
        $this->loginAsSecretaryAgain();
        $this->client->request('GET', "/api/events/{$id}");
        $guests = (array) $this->json()['guests'];
        self::assertSame(['Zoé', 'Robot', 'Robot'], array_column($guests, 'name'));
        $bot = $this->digString($guests, 1, 'id');

        $this->client->request('DELETE', "/api/events/{$id}/guests/{$bot}", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/events/{$id}");
        self::assertCount(2, (array) $this->json()['guests']);
        self::assertSame([['guest.remove', ['name' => 'Robot']]], array_map(
            static fn (array $entry): array => [$entry['action'], $entry['details']],
            $this->auditTrail(),
        ));
    }

    public function testVisitorsDiscoverUpcomingPublicEventsOnly(): void
    {
        $this->loginAsSecretary();
        $this->postJson('/api/events', self::payload(['title' => 'Soirée privée']));
        $private = $this->digString($this->json(), 'id');
        $this->postJson('/api/events', self::payload(['title' => 'Tournoi ouvert', 'public' => true, 'city' => 'Louhans']));
        $public = $this->digString($this->json(), 'id');
        $slug = substr($this->digString($this->json(), 'publicPath'), 3);
        $past = $this->pastEvent('Ancienne soirée publique');
        $past->edit(new EventDetails('Ancienne soirée publique', '', 'Club', $past->getStartsAt(), $past->getEndsAt(), null, true, 'Mâcon'), $past->getStartsAt()->modify('-1 day'));
        $this->entityManager()->flush();
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);

        // Sans compte : seulement les événements publics à venir, sans adresse
        $this->client->request('GET', '/api/public/events');
        self::assertResponseIsSuccessful();
        self::assertSame(['Tournoi ouvert'], array_column((array) $this->json()['items'], 'title'));
        self::assertArrayNotHasKey('location', (array) $this->dig($this->json(), 'items', 0));

        // Lien adhérent partagé : renvoie vers la page publique, jamais pour un événement privé
        $this->client->request('GET', "/api/public/events/by-id/{$public}");
        self::assertSame($slug, $this->json()['slug']);
        $this->client->request('GET', "/api/public/events/by-id/{$private}");
        self::assertResponseStatusCodeSame(404);
    }

    public function testVisitorsKeepTrackOfPastPublicEvents(): void
    {
        $this->pastPublicEvent('Tournoi de printemps', '2026-04-11 19:00');
        $this->pastPublicEvent('Soirée de rentrée', '2026-09-12 19:00');
        $this->pastEvent('Soirée privée passée', '2026-09-26 19:00');
        $this->pastPublicEvent('Soirée annulée', '2026-10-03 19:00')->cancel('Salle indisponible');
        $this->entityManager()->flush();

        // Du plus récent au plus ancien ; ni événement privé ni événement annulé, jamais l'adresse
        $this->client->request('GET', '/api/public/events');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['items']);
        self::assertSame(['Soirée de rentrée', 'Tournoi de printemps'], array_column((array) $this->json()['past'], 'title'));
        self::assertTrue($this->dig($this->json(), 'past', 0, 'past'));
        self::assertSame('Mâcon', $this->dig($this->json(), 'past', 0, 'city'));
        self::assertArrayNotHasKey('location', (array) $this->dig($this->json(), 'past', 0));
    }

    public function testUnconfirmedGuestAnswersAreForgotten(): void
    {
        $this->loginAsSecretary();
        $this->postJson('/api/events', self::payload(['public' => true, 'city' => 'Mâcon']));
        $slug = substr($this->digString($this->json(), 'publicPath'), 3);
        $this->guestAnswer($slug, ['email' => 'late@example.net']);
        self::assertResponseStatusCodeSame(202);

        $clock = $this->service('clock', MockClock::class);
        $clock->modify('+49 hours');
        $purged = $this->service(\App\ClubEvent\GuestResponses::class, \App\ClubEvent\GuestResponses::class)->purge();
        self::assertSame(1, $purged);
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

    /** Événement passé ouvert au public, avec son adresse /e/{slug} */
    private function pastPublicEvent(string $title, string $start): ClubEvent
    {
        $event = $this->pastEvent($title, $start);
        $event->edit(new EventDetails($title, 'Archive', 'Club', $event->getStartsAt(), $event->getEndsAt(), null, true, 'Mâcon'), $event->getStartsAt()->modify('-1 day'));
        $event->assignPublicAddress(substr(hash('sha256', $title), 0, 6));
        $event->releaseEvents();
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

    /** @return list<string> */
    private function storedPhotoFiles(): array
    {
        $root = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($root);
        $files = glob($root.'/var/storage/event-photos/*.webp');

        return false === $files ? [] : $files;
    }

    private function uploadPoster(string $eventId): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'poster');
        self::assertIsString($copy);
        copy(__DIR__.'/../Fixtures/avatar.jpg', $copy);

        $this->client->request('POST', "/api/events/{$eventId}/poster", files: ['poster' => new UploadedFile($copy, 'affiche.jpg', test: true)], server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
    }

    /** @param array<string, mixed> $overrides */
    private function guestAnswer(string $slug, array $overrides = []): void
    {
        $this->postJson("/api/public/events/{$slug}/responses", $overrides + [
            'name' => 'Invité', 'email' => 'guest@example.net', 'answer' => 'je-viens', 'consent' => true, 'website' => '',
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function tokenAction(string $action, string $token, array $extra = []): void
    {
        $this->postJson("/api/public/responses/{$action}", ['token' => $token] + $extra);
    }

    /** Jeton du dernier lien envoyé à cette adresse */
    private function linkSentTo(string $address): ?string
    {
        $token = null;
        foreach (self::getMailerMessages() as $message) {
            if ($message instanceof \Symfony\Component\Mime\Email && $address === ($message->getTo()[0] ?? null)?->getAddress()
                && 1 === preg_match('~/reponse#([A-Za-z0-9_-]{43})~', (string) $message->getTextBody(), $match)) {
                $token = $match[1];
            }
        }

        return $token;
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
