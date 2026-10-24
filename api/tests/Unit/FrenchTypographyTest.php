<?php

namespace App\Tests\Unit;

use App\Translation\FrenchTypography;
use PHPUnit\Framework\TestCase;

final class FrenchTypographyTest extends TestCase
{
    private const NBSP = FrenchTypography::NBSP;

    public function testHighPunctuationGuillemetsAndNumbersKeepTheirSpace(): void
    {
        self::assertSame(
            'Objet'.self::NBSP.': «'.self::NBSP.'Soirée'.self::NBSP.'»'.self::NBSP.'; vraiment'.self::NBSP.'? oui'.self::NBSP.'! −20'.self::NBSP.'% n°'.self::NBSP.'12',
            FrenchTypography::apply('Objet : « Soirée » ; vraiment ? oui ! −20 % n° 12'),
        );
    }

    public function testTimesLinksAndRepeatedRunsAreLeftAlone(): void
    {
        $text = 'À 20:30, voir https://skulldarts71.fr/?soiree=1';
        self::assertSame($text, FrenchTypography::apply($text));
        $once = FrenchTypography::apply('Note : « oui »');
        self::assertSame($once, FrenchTypography::apply($once));
    }

    public function testOnlyFrenchIsTouched(): void
    {
        self::assertSame('Objet'.self::NBSP.': test', FrenchTypography::forLocale('Objet : test', 'fr_FR'));
        self::assertSame('Subject : test', FrenchTypography::forLocale('Subject : test', 'en'));
    }
}
