<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ClubEvent\EventDetails;
use App\ClubEvent\PressDetails;
use App\ClubEvent\PressReview;
use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\User;
use App\Preference\Language;
use App\Translation\InvalidValue;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Coupures de presse : une photo de la galerie classée « Dans la presse », et ses détails. */
final class PressClippingTest extends TestCase
{
    private const NOW = '2026-11-09 12:00';

    public function testAPhotoBecomesAClippingWithItsDetailsThenGoesBackToTheGalleryWithout(): void
    {
        $photo = $this->photo();
        self::assertFalse($photo->isPressClipping());
        self::assertNull($photo->pressDetails());

        $photo->markAsPressClipping(new PressDetails(
            outlet: 'La Gazette de la Bresse',
            publishedOn: PressDetails::day('2026-11-03'),
            title: 'Une Halloween Party qui a fait le plein à Bellevesvre',
            credit: 'Texte et photos : Camille Martin',
        ));

        self::assertTrue($photo->isPressClipping());
        self::assertSame([
            'outlet' => 'La Gazette de la Bresse',
            'publishedOn' => '2026-11-03',
            'title' => 'Une Halloween Party qui a fait le plein à Bellevesvre',
            'credit' => 'Texte et photos : Camille Martin',
        ], $photo->toArray()['press']);

        $photo->returnToGallery();

        self::assertFalse($photo->isPressClipping());
        self::assertNull($photo->toArray()['press']);
        // Classée de nouveau : rien ne revient des anciens détails
        $photo->markAsPressClipping(new PressDetails());
        self::assertSame(['outlet' => null, 'publishedOn' => null, 'title' => null, 'credit' => null], $photo->toArray()['press']);
    }

    public function testNewDetailsReplaceTheOldOnesWhole(): void
    {
        $photo = $this->photo();
        $photo->markAsPressClipping(new PressDetails(outlet: 'L’Écho de la Seille', title: 'Halloween à Bellevesvre'));

        $photo->markAsPressClipping(new PressDetails(credit: 'Photo : Lucas Petit'));

        self::assertSame(['outlet' => null, 'publishedOn' => null, 'title' => null, 'credit' => 'Photo : Lucas Petit'], $photo->toArray()['press']);
    }

    public function testEveryDetailIsOptionalAndAnEmptyTextIsNotADetail(): void
    {
        $details = new PressDetails(outlet: '', publishedOn: null, title: '   ', credit: "\t");

        self::assertSame(['outlet' => null, 'publishedOn' => null, 'title' => null, 'credit' => null], $details->toArray());
    }

    public function testEachDetailFillsItsWholeColumn(): void
    {
        $details = new PressDetails(
            outlet: str_repeat('é', PressDetails::OUTLET_MAX),
            title: str_repeat('é', PressDetails::TITLE_MAX),
            credit: str_repeat('é', PressDetails::CREDIT_MAX),
        );

        self::assertSame(80, mb_strlen((string) $details->outlet));
        self::assertSame(200, mb_strlen((string) $details->title));
        self::assertSame(120, mb_strlen((string) $details->credit));
    }

    /** @return iterable<string, array{\Closure(): PressDetails}> */
    public static function detailsOverTheirLimit(): iterable
    {
        yield 'journal de 81 caractères' => [static fn (): PressDetails => new PressDetails(outlet: str_repeat('é', PressDetails::OUTLET_MAX + 1))];
        yield 'titre de 201 caractères' => [static fn (): PressDetails => new PressDetails(title: str_repeat('é', PressDetails::TITLE_MAX + 1))];
        yield 'crédit de 121 caractères' => [static fn (): PressDetails => new PressDetails(credit: str_repeat('é', PressDetails::CREDIT_MAX + 1))];
    }

    /** @param \Closure(): PressDetails $details */
    #[DataProvider('detailsOverTheirLimit')]
    public function testADetailLongerThanItsColumnIsRefusedNotCut(\Closure $details): void
    {
        $this->expectException(InvalidValue::class);
        $details();
    }

    /** @return iterable<string, array{string, string}> */
    public static function realDays(): iterable
    {
        yield 'un jour ordinaire' => ['2026-11-03', '2026-11-03'];
        yield 'un 29 février d\'année bissextile' => ['2028-02-29', '2028-02-29'];
        yield 'le premier jour accepté' => ['1900-01-01', '1900-01-01'];
        yield 'le dernier jour accepté' => ['2099-12-31', '2099-12-31'];
    }

    #[DataProvider('realDays')]
    public function testAPublicationDayIsAWholeCalendarDay(string $text, string $stored): void
    {
        self::assertSame($stored, (new PressDetails(publishedOn: PressDetails::day($text)))->toArray()['publishedOn']);
    }

    public function testNoDayWrittenMeansNoDate(): void
    {
        self::assertNull(PressDetails::day(null));
        self::assertNull(PressDetails::day(''));
    }

    /** @return iterable<string, array{string}> */
    public static function wrongDays(): iterable
    {
        yield 'un 30 février' => ['2026-02-30'];
        yield 'un 29 février d\'année commune' => ['2027-02-29'];
        yield 'un treizième mois' => ['2026-13-01'];
        yield 'des chiffres manquants' => ['2026-2-3'];
        yield 'l\'ordre français' => ['03/11/2026'];
        yield 'sans séparateurs' => ['20261103'];
        yield 'une heure en plus' => ['2026-11-03T10:00'];
        yield 'une espace devant' => [' 2026-11-03'];
        yield 'une fin de ligne' => ["2026-11-03\n"];
        yield 'un signe devant l\'année' => ['+2026-11-03'];
        yield 'des chiffres d\'une autre écriture' => ['٢٠٢٦-١١-٠٣'];
        yield 'un mot' => ['hier'];
    }

    #[DataProvider('wrongDays')]
    public function testAnythingElseThanAWholeDayIsRefusedNeverGuessed(string $text): void
    {
        $this->expectException(InvalidValue::class);
        PressDetails::day($text);
    }

    /** @return iterable<string, array{string}> */
    public static function daysOutOfRange(): iterable
    {
        yield 'avant 1900' => ['1899-12-31'];
        yield 'après 2099' => ['2100-01-01'];
        yield 'l\'an 1' => ['0001-01-01'];
    }

    #[DataProvider('daysOutOfRange')]
    public function testAPublicationDayOutsideTheTwoCenturiesIsRefused(string $text): void
    {
        $this->expectException(InvalidValue::class);
        new PressDetails(publishedOn: PressDetails::day($text));
    }

    public function testTheTimeOfADateNeverReachesTheClipping(): void
    {
        $details = new PressDetails(publishedOn: new \DateTimeImmutable('2026-11-03 23:59:59'));

        self::assertSame('2026-11-03 00:00:00', $details->publishedOn?->format('Y-m-d H:i:s'));
    }

    public function testTheReviewKeepsTheGalleryInArrivalOrderAndSortsClippingsByPublicationThenArrival(): void
    {
        $morning = $this->photo();
        $undatedFirst = $this->clipping(null);
        $november = $this->clipping('2026-11-03');
        $party = $this->photo();
        $october = $this->clipping('2026-10-31');
        $sameDayLater = $this->clipping('2026-11-03');
        $undatedLast = $this->clipping(null);

        $review = PressReview::of([$morning, $undatedFirst, $november, $party, $october, $sameDayLater, $undatedLast]);

        self::assertSame([$morning, $party], $review->gallery);
        self::assertSame([$october, $november, $sameDayLater, $undatedFirst, $undatedLast], $review->clippings);
    }

    private function clipping(?string $day): EventPhoto
    {
        $photo = $this->photo();
        $photo->markAsPressClipping(new PressDetails(outlet: 'La Gazette de la Bresse', publishedOn: PressDetails::day($day)));

        return $photo;
    }

    private function photo(): EventPhoto
    {
        $now = new \DateTimeImmutable(self::NOW);
        $member = User::registerViaOAuth(EmailAddress::fromString('alice@example.com'), Language::French, $now);
        $start = new \DateTimeImmutable('2026-10-31 19:00');
        $event = ClubEvent::schedule($member, new EventDetails(
            title: 'Halloween Party',
            description: 'Goûter, boum, soirée déguisée',
            location: 'Salle des fêtes, Bellevesvre',
            startsAt: $start,
            endsAt: $start->modify('+5 hours'),
        ), $start->modify('-1 month'));

        return new EventPhoto(event: $event, uploader: $member, largeId: str_repeat('a', 32), thumbId: str_repeat('b', 32), width: 1600, height: 1200, now: $now);
    }
}
