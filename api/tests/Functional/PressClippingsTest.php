<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\Role;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * « Dans la presse » : qui organise l'événement classe une photo de la galerie parmi les coupures
 * de presse ; les membres la voient au-dessus de la galerie, plus dans la galerie. Horloge figée au
 * 15/10/2026 12:00 UTC.
 */
final class PressClippingsTest extends ApiTestCase
{
    use BoardScenario;

    private const SECRETARY = 'secretaire@example.com';
    private const PRESIDENT = 'presidente@example.com';

    public function testAPlainMemberCannotFileAPhotoInThePressNotEvenHerOwn(): void
    {
        $this->createUser('alice@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->memberLogin('alice@example.com');
        [$photo] = $this->uploadPhotos($evening, 1);

        $this->request('PUT', "/api/event-photos/{$photo}/press", ['outlet' => 'La Gazette de la Bresse']);
        self::assertResponseStatusCodeSame(403);
        $this->request('DELETE', "/api/event-photos/{$photo}/press");
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', "/api/events/{$evening}");
        self::assertCount(1, (array) $this->json()['photos']);
        self::assertSame([], $this->json()['pressClippings']);
        self::assertFalse($this->json()['canManage']);
    }

    public function testAnOrganizerFilesAPhotoAndMembersSeeItAboveTheGalleryOnly(): void
    {
        $this->board([self::SECRETARY => Role::Secretary]);
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->memberLogin('alice@example.com');
        [$page, $party] = $this->uploadPhotos($evening, 2);

        $this->loginAs(self::SECRETARY);
        $this->request('PUT', "/api/event-photos/{$page}/press", [
            'outlet' => 'La Gazette de la Bresse',
            'publishedOn' => '2026-10-13',
            'title' => 'Une Halloween Party qui a fait le plein à Bellevesvre',
            'credit' => 'Texte et photos : Camille Martin',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-13', $this->dig($this->json(), 'press', 'publishedOn'));

        $this->memberLogin('bob@example.com');
        $this->client->request('GET', "/api/events/{$evening}");
        $detail = $this->json();
        self::assertSame([$party], array_column((array) $detail['photos'], 'id'), 'La coupure a quitté la galerie');
        self::assertSame([$page], array_column((array) $detail['pressClippings'], 'id'));
        $clipping = $this->dig($detail, 'pressClippings', 0);
        self::assertIsArray($clipping);
        // Les mêmes fichiers que dans la galerie, servis sous le même droit (membres validés)
        self::assertSame("/api/event-photos/{$page}/thumb", $clipping['thumbUrl']);
        self::assertSame("/api/event-photos/{$page}/large", $clipping['largeUrl']);
        self::assertGreaterThan(0, $clipping['width']);
        self::assertGreaterThan(0, $clipping['height']);
        self::assertSame([
            'outlet' => 'La Gazette de la Bresse',
            'publishedOn' => '2026-10-13',
            'title' => 'Une Halloween Party qui a fait le plein à Bellevesvre',
            'credit' => 'Texte et photos : Camille Martin',
        ], $clipping['press']);
        self::assertFalse($clipping['mine']);
        self::assertNull($this->dig($detail, 'photos', 0, 'press'));
        $this->client->request('GET', "/api/event-photos/{$page}/large");
        self::assertResponseIsSuccessful();

        self::assertSame([['photo.press', 'Soirée fléchettes', ['author' => 'alice']]], array_map(
            static fn (array $entry): array => [$entry['action'], $entry['target'], $entry['details']],
            $this->auditTrail(),
        ));
    }

    public function testThePresidencyManagesTooAndAClippingGoesBackToTheGallery(): void
    {
        $this->board([self::PRESIDENT => Role::President]);
        $this->createUser('alice@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->memberLogin('alice@example.com');
        [$photo] = $this->uploadPhotos($evening, 1);

        $this->loginAs(self::PRESIDENT);
        // Tous les détails sont facultatifs : un corps vide classe la photo quand même
        $this->request('PUT', "/api/event-photos/{$photo}/press", []);
        self::assertResponseIsSuccessful();
        self::assertSame(['outlet' => null, 'publishedOn' => null, 'title' => null, 'credit' => null], $this->json()['press']);

        $this->request('DELETE', "/api/event-photos/{$photo}/press");
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame([$photo], array_column((array) $this->json()['photos'], 'id'));
        self::assertSame([], $this->json()['pressClippings']);

        // Déjà dans la galerie : rien ne change, rien de plus au journal
        $this->request('DELETE', "/api/event-photos/{$photo}/press");
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['photo.press', 'photo.unpress'], array_column($this->auditTrail(), 'action'));
    }

    public function testClippingsGoFromTheOldestPublicationToTheNewestAndUndatedOnesLast(): void
    {
        $this->board([self::SECRETARY => Role::Secretary]);
        $this->createUser('alice@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->memberLogin('alice@example.com');
        // Quatre envois successifs, dans cet ordre d'arrivée
        $undated = $this->uploadPhotos($evening, 1)[0];
        $third = $this->uploadPhotos($evening, 1)[0];
        $first = $this->uploadPhotos($evening, 1)[0];
        $sameDayLater = $this->uploadPhotos($evening, 1)[0];

        $this->loginAs(self::SECRETARY);
        foreach ([$undated => null, $third => '2026-10-14', $first => '2026-10-12', $sameDayLater => '2026-10-14'] as $id => $day) {
            $this->request('PUT', "/api/event-photos/{$id}/press", ['outlet' => 'L’Écho de la Seille', 'publishedOn' => $day]);
            self::assertResponseIsSuccessful();
        }

        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame([$first, $third, $sameDayLater, $undated], array_column((array) $this->json()['pressClippings'], 'id'));
        self::assertSame([], $this->json()['photos']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function hostileDetails(): iterable
    {
        yield 'journal trop long' => [['outlet' => str_repeat('é', 81)]];
        yield 'titre trop long' => [['title' => str_repeat('é', 201)]];
        yield 'crédit trop long' => [['credit' => str_repeat('é', 121)]];
        yield 'journal en nombre' => [['outlet' => 42]];
        yield 'titre en liste' => [['title' => ['Halloween']]];
        yield 'crédit en objet' => [['credit' => ['text' => 'CLP']]];
        yield 'date en nombre' => [['publishedOn' => 20261103]];
        yield 'un 30 février' => [['publishedOn' => '2026-02-30']];
        yield 'date à la française' => [['publishedOn' => '03/11/2026']];
        yield 'date et heure' => [['publishedOn' => '2026-11-03T10:00:00+01:00']];
        yield 'date d\'avant 1900' => [['publishedOn' => '1899-12-31']];
        yield 'date d\'après 2099' => [['publishedOn' => '2100-01-01']];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('hostileDetails')]
    public function testEveryDetailIsCheckedAtTheDoor(array $payload): void
    {
        [$evening, $photo] = $this->photoForTheSecretary();

        $this->request('PUT', "/api/event-photos/{$photo}/press", $payload);

        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame([$photo], array_column((array) $this->json()['photos'], 'id'), 'Refusée : la photo reste dans la galerie');
        self::assertSame([], $this->json()['pressClippings']);
    }

    public function testDetailsFillTheirWholeLengthAndAreCleanedFirst(): void
    {
        [, $photo] = $this->photoForTheSecretary();

        $this->request('PUT', "/api/event-photos/{$photo}/press", [
            'outlet' => "La Gazette de la Bresse\u{200B}\u{202E}",
            'publishedOn' => '2028-02-29',
            'title' => str_repeat('é', 200),
            'credit' => " Texte :\r\n Camille Martin ",
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'outlet' => 'La Gazette de la Bresse',
            'publishedOn' => '2028-02-29',
            'title' => str_repeat('é', 200),
            'credit' => 'Texte : Camille Martin',
        ], $this->json()['press']);
    }

    public function testAWriteFromAnotherSiteOrWithoutASessionIsRefused(): void
    {
        [, $photo] = $this->photoForTheSecretary();

        $this->client->request('PUT', "/api/event-photos/{$photo}/press", server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://evil.example'], content: '{}');
        self::assertResponseStatusCodeSame(403, 'Origine non autorisée');
        $this->client->request('DELETE', "/api/event-photos/{$photo}/press", server: ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertResponseStatusCodeSame(403);

        $this->client->getCookieJar()->clear();
        $this->request('PUT', "/api/event-photos/{$photo}/press", []);
        self::assertResponseStatusCodeSame(401);
        self::assertSame([], array_column($this->auditTrail(), 'action'));
    }

    public function testAClippingHiddenOnRequestStaysWithThePresidencyAndItsAuthor(): void
    {
        $this->board([self::SECRETARY => Role::Secretary, self::PRESIDENT => Role::President]);
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->memberLogin('alice@example.com');
        [$photo] = $this->uploadPhotos($evening, 1);
        $this->loginAs(self::SECRETARY);
        $this->request('PUT', "/api/event-photos/{$photo}/press", ['outlet' => 'La Gazette de la Bresse']);

        // Droit à l'image : Bob apparaît sur la coupure et en demande le retrait
        $this->memberLogin('bob@example.com');
        $this->request('POST', "/api/event-photos/{$photo}/removal-request");
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame([], $this->json()['pressClippings']);
        self::assertSame([], $this->json()['photos']);

        // La secrétaire ne la voit plus : elle ne peut plus la changer
        $this->loginAs(self::SECRETARY);
        $this->request('PUT', "/api/event-photos/{$photo}/press", ['outlet' => 'L’Écho de la Seille']);
        self::assertResponseStatusCodeSame(403);

        $this->memberLogin('alice@example.com');
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertTrue($this->dig($this->json(), 'pressClippings', 0, 'hidden'));

        $this->loginAs(self::PRESIDENT);
        $this->client->request('GET', "/api/events/{$evening}");
        self::assertSame('bob', $this->dig($this->json(), 'pressClippings', 0, 'hiddenBy'));
        self::assertSame('La Gazette de la Bresse', $this->dig($this->json(), 'pressClippings', 0, 'press', 'outlet'));
    }

    /**
     * Une photo d'Alice dans la galerie d'une soirée passée, et la secrétaire connectée.
     *
     * @return array{string, string} la soirée, la photo
     */
    private function photoForTheSecretary(): array
    {
        $this->board([self::SECRETARY => Role::Secretary]);
        $this->createUser('alice@example.com');
        $evening = $this->evening('2026-10-10 20:00')->getId()->toRfc4122();
        $this->memberLogin('alice@example.com');
        [$photo] = $this->uploadPhotos($evening, 1);
        $this->loginAs(self::SECRETARY);

        return [$evening, $photo];
    }

    private function memberLogin(string $email): void
    {
        $this->client->getCookieJar()->clear();
        $this->login($email, self::PASSWORD);
        self::assertResponseIsSuccessful();
    }

    /** @return list<string> identifiants des photos envoyées, dans l'ordre */
    private function uploadPhotos(string $eventId, int $count): array
    {
        $files = [];
        for ($i = 0; $i < $count; ++$i) {
            $copy = tempnam(sys_get_temp_dir(), 'photo');
            self::assertIsString($copy);
            copy(__DIR__.'/../Fixtures/avatar.jpg', $copy);
            $files[] = new UploadedFile($copy, "coupure{$i}.jpg", test: true);
        }
        $this->client->request('POST', "/api/events/{$eventId}/photos", files: ['photos' => $files], server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(201);

        return array_map(fn (mixed $item): string => $this->digString($item, 'id'), array_values((array) $this->json()['items']));
    }
}
