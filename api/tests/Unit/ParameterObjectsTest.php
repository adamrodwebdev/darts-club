<?php

namespace App\Tests\Unit;

use App\Admin\BroadcastAudience;
use App\ClubEvent\ArchiveSearch;
use App\ClubEvent\EventChange;
use App\Notification\Category;
use PHPUnit\Framework\TestCase;

final class ParameterObjectsTest extends TestCase
{
    public function testArchiveSearchReadsTheQueryString(): void
    {
        $search = ArchiveSearch::fromQuery('2026-2027', '  Soirée de rentrée  ');
        self::assertSame('2026-2027', $search->season?->label());
        self::assertSame('Soirée de rentrée', $search->query);
        self::assertSame(100, $search->limit);

        self::assertNull(ArchiveSearch::fromQuery('', 'x')->season);
        self::assertSame(ArchiveSearch::QUERY_MAX, mb_strlen(ArchiveSearch::fromQuery(null, str_repeat('é', 200))->query));
    }

    public function testArchiveSearchRejectsAMalformedSeason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ArchiveSearch::fromQuery('2026-2028', '');
    }

    public function testBroadcastAudienceIsEveryoneOrAChosenList(): void
    {
        self::assertTrue(BroadcastAudience::allMembers()->isEveryone());

        $chosen = BroadcastAudience::members(['a1', 'b2']);
        self::assertFalse($chosen->isEveryone());
        self::assertSame(['a1', 'b2'], $chosen->userIds);
    }

    public function testEachEventChangeHasItsNotification(): void
    {
        self::assertSame([Category::Event, 'event.scheduled'], [EventChange::Scheduled->category(), EventChange::Scheduled->titleKey()]);
        self::assertSame([Category::Event, 'event.rescheduled'], [EventChange::Rescheduled->category(), EventChange::Rescheduled->titleKey()]);
        self::assertSame([Category::Cancellation, 'event.cancelled'], [EventChange::Cancelled->category(), EventChange::Cancelled->titleKey()]);
    }
}
