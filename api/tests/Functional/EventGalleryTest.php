<?php

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Enum\Role;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Galerie : vidéos de la présidence envoyées par morceaux (conversion simulée par FakeVideoTranscoder),
 * formats les plus légers, demandes de retrait. Horloge figée au 15/10/2026 12:00 UTC.
 */
final class EventGalleryTest extends ApiTestCase
{
    use BoardScenario;

    private const PRESIDENT = 'presidente@example.com';

    public function testOnlyThePresidencyPublishesVideosAndPublishesBeforeTheEvening(): void
    {
        $this->board([self::PRESIDENT => Role::President]);
        $this->createUser('alice@example.com');
        $upcoming = $this->evening('2026-11-21 20:00')->getId()->toRfc4122();

        $this->memberLogin('alice@example.com');
        $this->request('POST', "/api/events/{$upcoming}/videos", self::declaration(1000));
        self::assertResponseStatusCodeSame(403, 'Un membre ne publie pas de vidéo');
        $this->uploadPhoto($upcoming);
        self::assertResponseStatusCodeSame(403, 'Un membre attend le début de la soirée pour ses photos');
        $this->client->request('GET', "/api/events/{$upcoming}");
        self::assertFalse($this->dig($this->json(), 'gallery', 'canAddPhotos'));
        self::assertFalse($this->dig($this->json(), 'gallery', 'canAddVideos'));

        $this->loginAs(self::PRESIDENT);
        $this->uploadPhoto($upcoming);
        self::assertResponseStatusCodeSame(201, 'La présidence publie avant la soirée');
        $this->request('POST', "/api/events/{$upcoming}/videos", self::declaration(1000));
        self::assertResponseStatusCodeSame(201);
        self::assertSame('envoi', $this->json()['status']);
        self::assertSame(8 * 1024 * 1024, $this->json()['chunkSize']);
    }

    public function testAVideoArrivesInChunksResumesAfterACutAndPlaysInTheLightestFormat(): void
    {
        $this->board([self::PRESIDENT => Role::President]);
        $this->createUser('alice@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $video = self::fakeVideo(5000);

        $this->loginAs(self::PRESIDENT);
        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(\strlen($video)));
        $id = $this->digString($this->json(), 'id');

        $this->sendChunk($id, 0, substr($video, 0, 40));
        self::assertResponseIsSuccessful();
        self::assertSame(40, $this->json()['received']);

        // Le même morceau renvoyé après une coupure : refusé, l'appareil relit la position
        $this->sendChunk($id, 0, substr($video, 0, 40));
        self::assertResponseStatusCodeSame(409);
        $this->client->request('GET', "/api/event-videos/{$id}");
        self::assertSame(40, $this->json()['received']);

        $this->sendChunk($id, 40, substr($video, 40));
        self::assertResponseIsSuccessful();
        self::assertSame('prete', $this->json()['status'], 'Conversion faite (worker synchrone en test)');
        self::assertSame("/api/event-videos/{$id}/file/av1", $this->json()['av1Url'], 'AV1 plus léger proposé en premier');
        self::assertSame(1280, $this->json()['width']);

        // Lecture avec reprise : Safari sur iPhone demande des plages d'octets
        $this->client->request('GET', "/api/event-videos/{$id}/file", server: ['HTTP_RANGE' => 'bytes=0-9']);
        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Type', 'video/mp4');
        self::assertResponseHeaderSame('Accept-Ranges', 'bytes');
        self::assertResponseHeaderSame('Content-Range', 'bytes 0-9/4000');
        $this->client->request('GET', "/api/event-videos/{$id}/file/av1");
        self::assertResponseIsSuccessful();

        // Aperçu : AVIF au navigateur qui l'annonce (si GD sait l'encoder), WebP sinon
        $this->client->request('GET', "/api/event-videos/{$id}/poster", server: ['HTTP_ACCEPT' => 'image/avif,image/webp,*/*']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', \function_exists('imageavif') ? 'image/avif' : 'image/webp');
        self::assertStringContainsString('Accept', (string) $this->client->getResponse()->headers->get('Vary'));
        $this->client->request('GET', "/api/event-videos/{$id}/poster", server: ['HTTP_ACCEPT' => 'image/webp,*/*']);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        // Les membres la voient, sans pouvoir en publier
        $this->memberLogin('alice@example.com');
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame('prete', $this->dig($this->json(), 'videos', 0, 'status'));
        self::assertTrue($this->dig($this->json(), 'gallery', 'canAddPhotos'));
        self::assertFalse($this->dig($this->json(), 'gallery', 'canAddVideos'));

        // Supprimée par la présidence : fichiers effacés
        $this->loginAs(self::PRESIDENT);
        $this->client->request('DELETE', "/api/event-videos/{$id}", server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/event-videos/{$id}/file");
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheServerChecksTheDeclarationTheSignatureAndTheRealDuration(): void
    {
        $this->board([self::PRESIDENT => Role::President]);
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->loginAs(self::PRESIDENT);

        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(1000, durationMs: 61_000));
        self::assertResponseStatusCodeSame(422, 'Annoncée trop longue');
        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(501 * 1024 * 1024));
        self::assertResponseStatusCodeSame(422, 'Annoncée trop lourde');
        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(1000, mediaType: 'text/plain'));
        self::assertResponseStatusCodeSame(422, 'Pas une vidéo');

        // Annoncée courte mais longue en vrai : refusée à la conversion
        $long = self::fakeVideo(65_000);
        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(\strlen($long)));
        $this->sendChunk($this->digString($this->json(), 'id'), 0, $long);
        self::assertSame('echec', $this->json()['status']);
        self::assertSame('trop-longue', $this->json()['failure']);

        // Un fichier qui n'est ni MP4/MOV ni WebM n'atteint jamais le décodeur
        $script = '#EXTM3U'."\n".'#EXT-X-MEDIA-SEQUENCE:0'."\n".'concat:/etc/passwd';
        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(\strlen($script)));
        $this->sendChunk($this->digString($this->json(), 'id'), 0, $script);
        self::assertSame('echec', $this->json()['status']);
        self::assertSame('illisible', $this->json()['failure']);
    }

    public function testOnlyTheUploaderSendsTheChunks(): void
    {
        $this->board([self::PRESIDENT => Role::President, 'admin@example.com' => Role::Admin]);
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->loginAs(self::PRESIDENT);
        $this->request('POST', "/api/events/{$evening}/videos", self::declaration(100));
        $id = $this->digString($this->json(), 'id');

        $this->loginAs('admin@example.com');
        $this->sendChunk($id, 0, str_repeat('x', 10));
        self::assertResponseStatusCodeSame(403);
    }

    public function testARemovalRequestHidesThePhotoUntilThePresidencyDecides(): void
    {
        $this->board([self::PRESIDENT => Role::President]);
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();

        $this->memberLogin('alice@example.com');
        $this->uploadPhoto($evening);
        $photoId = $this->digString($this->json(), 'items', 0, 'id');

        $this->memberLogin('bob@example.com');
        $this->request('POST', "/api/event-photos/{$photoId}/removal-request");
        self::assertResponseStatusCodeSame(204);
        $this->request('POST', "/api/event-photos/{$photoId}/removal-request");
        self::assertResponseStatusCodeSame(403, 'Déjà masquée');
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame([], $this->json()['photos'], 'Masquée tout de suite');
        $this->client->request('GET', "/api/event-photos/{$photoId}/thumb");
        self::assertResponseStatusCodeSame(403);

        // La présidence est prévenue
        $notifications = $this->entityManager()->getRepository(Notification::class)->findAll();
        self::assertCount(1, $notifications);
        self::assertStringContainsString('Demande de retrait', $notifications[0]->toArray()['title']);

        // Elle voit la photo masquée et la remet en ligne
        $this->loginAs(self::PRESIDENT);
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertTrue($this->dig($this->json(), 'photos', 0, 'hidden'));
        self::assertSame('bob', $this->dig($this->json(), 'photos', 0, 'hiddenBy'));
        $this->request('POST', "/api/event-photos/{$photoId}/restore");
        self::assertResponseStatusCodeSame(204);

        $this->memberLogin('bob@example.com');
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertCount(1, (array) $this->json()['photos']);
        self::assertSame([['photo.hide', ['author' => 'alice']], ['photo.restore', ['author' => 'alice']]], array_map(
            static fn (array $entry): array => [$entry['action'], $entry['details']],
            $this->auditTrail(),
        ));
    }

    /** @return array<string, mixed> */
    private static function declaration(int $size, int $durationMs = 5000, string $mediaType = 'video/mp4'): array
    {
        return ['fileName' => 'IMG_2041.MOV', 'size' => $size, 'durationMs' => $durationMs, 'mediaType' => $mediaType];
    }

    /** Signature MP4 réelle (boîte « ftyp »), puis la durée réelle lue par FakeVideoTranscoder */
    private static function fakeVideo(int $durationMs): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat('v', 60).'DUREE='.$durationMs;
    }

    private function memberLogin(string $email): void
    {
        $this->client->getCookieJar()->clear();
        $this->login($email, self::PASSWORD);
    }

    private function sendChunk(string $videoId, int $offset, string $bytes): void
    {
        $this->client->request('PUT', "/api/event-videos/{$videoId}/chunks/{$offset}", server: [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_ORIGIN' => self::FRONT_ORIGIN,
        ], content: $bytes);
    }

    private function uploadPhoto(string $eventId): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'photo');
        self::assertIsString($copy);
        copy(__DIR__.'/../Fixtures/avatar.jpg', $copy);
        $this->client->request('POST', "/api/events/{$eventId}/photos", files: ['photos' => [new UploadedFile($copy, 'soiree.jpg', test: true)]], server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
    }
}
