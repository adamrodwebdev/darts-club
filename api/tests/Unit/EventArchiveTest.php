<?php

namespace App\Tests\Unit;

use App\ClubEvent\EventDetails;
use App\ClubEvent\RegistrationException;
use App\Entity\ClubEvent;
use App\Entity\User;
use App\Media\ImageUpload;
use App\Media\InvalidImageException;
use App\Preference\Language;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Archive d'un événement (photos, posts) et images reçues des formulaires. */
final class EventArchiveTest extends TestCase
{
    private const NOW = '2026-10-23 12:00';

    public function testTheArchiveOpensOnceTheEventHasStarted(): void
    {
        $event = $this->eventOn('2026-11-14 19:00');

        $this->expectException(RegistrationException::class);
        $event->assertArchiveOpen(new \DateTimeImmutable(self::NOW));
    }

    public function testAStartedEventTakesPhotosAndPosts(): void
    {
        $event = $this->eventOn('2026-11-14 19:00');

        $event->assertArchiveOpen(new \DateTimeImmutable('2026-11-14 19:30'));
        $this->addToAssertionCount(1);
    }

    public function testACancelledEventKeepsItsArchiveClosed(): void
    {
        $event = $this->eventOn('2026-11-14 19:00');
        $event->cancel('Salle indisponible');

        $this->expectException(RegistrationException::class);
        $event->assertArchiveOpen(new \DateTimeImmutable('2026-11-15 10:00'));
    }

    public function testAnImageThatArrivedWholeIsKept(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        try {
            self::assertSame($path, ImageUpload::from(new UploadedFile($path, 'affiche.jpg', test: true))->path);
        } finally {
            unlink($path);
        }
    }

    public function testAnImageCutShortIsRefusedByName(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        try {
            ImageUpload::from(new UploadedFile($path, 'soiree.png', error: \UPLOAD_ERR_PARTIAL, test: true));
            self::fail('Envoi incomplet accepté');
        } catch (InvalidImageException $e) {
            self::assertStringContainsString('soiree.png', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    private function eventOn(string $startsAt): ClubEvent
    {
        $now = new \DateTimeImmutable(self::NOW);
        $organizer = User::registerViaOAuth(EmailAddress::fromString('alice@example.com'), Language::French, $now);
        $start = new \DateTimeImmutable($startsAt);

        return ClubEvent::schedule($organizer, new EventDetails(
            title: 'Soirée fléchettes',
            description: 'Parties libres',
            location: 'Bar du club',
            startsAt: $start,
            endsAt: $start->modify('+4 hours'),
        ), $now);
    }
}
