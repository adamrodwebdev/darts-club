<?php

namespace App\Tests\Integration;

use App\Membership\Money;
use App\Messaging\Mailbox;
use App\Notification\Category;
use App\Notification\NotificationContent;
use App\Notification\TranslatedNotification;
use App\Translation\LocalizedDate;
use App\Translation\LocalizedMoney;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Une même notification, rendue dans la langue de chaque destinataire. */
final class NotificationLocalizationTest extends KernelTestCase
{
    public function testApplicationNotificationIsRenderedPerRecipientLanguage(): void
    {
        $notification = new TranslatedNotification(
            Category::Event,
            new TranslatableMessage('event.scheduled', ['%title%' => 'Soirée fléchettes'], 'notifications'),
            new TranslatableMessage('event.body', [
                '%date%' => new LocalizedDate(new \DateTimeImmutable('2026-10-16T18:00:00+00:00')),
                '%location%' => 'Bar du club',
            ], 'notifications'),
            '/evenements',
        );

        $fr = $notification->localize($this->translator(), 'fr');
        // Français : espace insécable avant les deux-points ; anglais : texte tel qu'écrit
        self::assertSame("[Événement] Nouvel événement\u{00A0}: Soirée fléchettes", $fr->labelledTitle());
        self::assertSame('Vendredi 16 octobre à 20:00 · Bar du club', $fr->body);

        $en = $notification->localize($this->translator(), 'en');
        self::assertSame('[Event] New event: Soirée fléchettes', $en->labelledTitle());
        self::assertSame('Friday 16 October at 20:00 · Bar du club', $en->body);
    }

    public function testAmountsAndMailboxesFollowTheLanguage(): void
    {
        $title = new TranslatableMessage('message.to_mailbox', ['%author%' => 'Alice', '%mailbox%' => Mailbox::Treasurer], 'notifications');
        self::assertSame('Alice → Trésorerie', $title->trans($this->translator(), 'fr'));
        self::assertSame('Alice → Treasury', $title->trans($this->translator(), 'en'));

        $amount = new LocalizedMoney(Money::fromDecimal('30.5'));
        self::assertSame('€30.50', $amount->trans($this->translator(), 'en'));
        self::assertSame("30,50\u{a0}€", $amount->trans($this->translator(), 'fr'));
    }

    public function testMemberWrittenContentIsLeftUntouched(): void
    {
        $content = new NotificationContent(Category::Meeting, 'Assemblée générale', 'Samedi au local.');

        $en = $content->localize($this->translator(), 'en');
        self::assertSame('[Meeting] Assemblée générale', $en->labelledTitle());
        self::assertSame('Samedi au local.', $en->body);
    }

    private function translator(): TranslatorInterface
    {
        $translator = self::getContainer()->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return $translator;
    }
}
