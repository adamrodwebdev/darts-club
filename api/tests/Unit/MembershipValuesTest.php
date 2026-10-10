<?php

namespace App\Tests\Unit;

use App\Membership\Money;
use App\Membership\Season;
use PHPUnit\Framework\TestCase;

final class MembershipValuesTest extends TestCase
{
    public function testSeasonRunsFromSeptemberToAugust(): void
    {
        self::assertSame('2026-2027', Season::containing(new \DateTimeImmutable('2026-09-01'))->label());
        self::assertSame('2025-2026', Season::containing(new \DateTimeImmutable('2026-08-31 23:59'))->label());

        $season = Season::fromLabel('2026-2027');
        self::assertSame('2026-10-01', $season->dueDate()->format('Y-m-d'));
        self::assertSame('2027-08-31 23:59:59', $season->endsAt()->format('Y-m-d H:i:s'));
    }

    public function testInvalidSeasonLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Season::fromLabel('2026-2028');
    }

    public function testMoneyParsesFrenchAndEnglishDecimals(): void
    {
        self::assertSame(3050, Money::fromDecimal('30,5')->cents);
        self::assertSame(3050, Money::fromDecimal('30.50')->cents);
        self::assertSame(3000, Money::fromDecimal('30')->cents);
        self::assertSame('30.05', Money::cents(3005)->toDecimal());
        self::assertSame(0, Money::cents(3000)->remainingAfter(Money::cents(4000))->cents);
    }

    public function testMoneyRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromDecimal('-5');
    }
}
