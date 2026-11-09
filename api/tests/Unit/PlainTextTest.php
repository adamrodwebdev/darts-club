<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Text\PlainText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlainTextTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function invisibleCharacters(): iterable
    {
        yield 'un caractère de contrôle' => ["A\u{0000}na", 'Ana'];
        yield 'un effacement' => ["A\u{007F}na", 'Ana'];
        yield 'une inversion droite-gauche' => ["A\u{202E}na", 'Ana'];
        yield 'un isolat bidirectionnel' => ["A\u{2066}na\u{2069}", 'Ana'];
        yield 'une marque d\'ordre des octets' => ["\u{FEFF}Ana", 'Ana'];
        yield 'une espace de largeur nulle' => ["A\u{200B}na", 'Ana'];
        yield 'un caractère d\'étiquette' => ["A\u{E0041}na", 'Ana'];
        yield 'des espaces en trop' => ['  Ana   Lima ', 'Ana Lima'];
        yield 'des espaces insécables' => ["\u{00A0}Ana\u{202F}Lima\u{00A0}", 'Ana Lima'];
        yield 'un retour à la ligne' => ["Ana\r\nLima", 'Ana Lima'];
        yield 'un accent décomposé' => ["Le\u{0301}a", 'Léa'];
        yield 'un invisible au milieu d\'un accent' => ["Le\u{200B}\u{0301}a", 'Léa'];
    }

    #[DataProvider('invisibleCharacters')]
    public function testALineLosesWhatCannotBeSeen(string $text, string $expected): void
    {
        self::assertSame($expected, PlainText::line($text));
    }

    public function testEmojiJoinedByZeroWidthJoinersStay(): void
    {
        self::assertSame('👨‍👩‍👧 équipe', PlainText::line('👨‍👩‍👧 équipe'));
    }

    public function testMarkupStaysAsWrittenToBeEscapedWhereItIsShown(): void
    {
        self::assertSame('<b>Ana</b> & co', PlainText::line('<b>Ana</b> & co'));
    }

    public function testSeveralLinesKeepTheirBreaksAndTabsUnifiedToLineFeeds(): void
    {
        self::assertSame("Un\nDeux\nTrois\n\tQuatre", PlainText::lines("  Un\r\nDeux\rTrois\n\tQuatre\u{0007}  "));
    }

    public function testInvalidUtf8IsLeftForTheLengthConstraintToRefuse(): void
    {
        self::assertSame("A\xC3na", PlainText::line("A\xC3na"));
    }

    /** @return iterable<string, array{string}> */
    public static function messyTexts(): iterable
    {
        yield 'contrôles et retours' => ["A\u{0000}na  Lima\r\n"];
        yield 'invisible dans un accent' => ["Le\u{200B}\u{0301}a"];
        yield 'bidirectionnel et BOM' => ["\u{FEFF}  \u{202E} x \u{2069} "];
        yield 'accents empilés' => ["e\u{0301}\u{0301}"];
    }

    #[DataProvider('messyTexts')]
    public function testCleaningTwiceChangesNothing(string $text): void
    {
        $once = PlainText::line($text);
        self::assertSame($once, PlainText::line($once));
        $lines = PlainText::lines($text);
        self::assertSame($lines, PlainText::lines($lines));
    }
}
