<?php

namespace App\Tests\Unit;

use App\Entity\Membership;
use App\Entity\User;
use App\Membership\MembershipStatus;
use App\Membership\Money;
use App\Membership\PaymentDetails;
use App\Membership\PaymentMethod;
use App\Membership\Season;
use App\Membership\SeasonFee;
use App\Preference\Language;
use App\ValueObject\EmailAddress;
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

    public function testPaymentDetailsNormaliseTheReference(): void
    {
        $paidAt = new \DateTimeImmutable('2026-09-05');

        self::assertNull((new PaymentDetails(Money::cents(3000), PaymentMethod::Cash, $paidAt, '   '))->reference);
        self::assertSame('Virement', (new PaymentDetails(Money::cents(3000), PaymentMethod::Transfer, $paidAt, ' Virement '))->reference);
    }

    public function testPaymentDetailsRejectAZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentDetails(Money::zero(), PaymentMethod::Cash, new \DateTimeImmutable('2026-09-05'));
    }

    public function testPaymentsAreDatedWithTheGivenTime(): void
    {
        $now = new \DateTimeImmutable('2026-10-15 12:00');
        $user = User::registerViaOAuth(EmailAddress::fromString('bob@example.com'), Language::French, $now);
        $membership = Membership::open($user, new SeasonFee(Season::fromLabel('2026-2027'), Money::cents(3000)), $now);

        $membership->recordPayment(new PaymentDetails(Money::cents(3000), PaymentMethod::Cash, $now->modify('-1 day')), null, $now);
        self::assertSame(MembershipStatus::Paid, $membership->status());

        [$payment] = $membership->payments();
        $membership->cancelPayment($payment, 'Erreur de saisie', $now->modify('+1 hour'));

        self::assertEquals($now, $membership->getCreatedAt());
        self::assertEquals($now, $payment->recordedAt());
        self::assertEquals($now->modify('+1 hour'), $payment->cancelledAt());
        self::assertSame(MembershipStatus::Unpaid, $membership->status());
    }
}
