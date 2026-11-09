<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\RequestFields;
use App\Translation\Unprocessable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RequestFieldsTest extends TestCase
{
    /** @param array<string, mixed> $body */
    private static function fields(array $body): RequestFields
    {
        return RequestFields::of(Request::create('/', 'POST', content: json_encode($body, \JSON_THROW_ON_ERROR)));
    }

    /** @return iterable<string, array{mixed}> */
    public static function notText(): iterable
    {
        yield 'un nombre' => [12];
        yield 'un décimal' => [12.5];
        yield 'un booléen' => [true];
        yield 'une liste' => [['Ana']];
        yield 'un objet' => [['name' => 'Ana']];
    }

    #[DataProvider('notText')]
    public function testAFieldThatIsNotTextIsRefused(mixed $value): void
    {
        $this->expectException(Unprocessable::class);
        $this->expectExceptionMessage('Le champ « reason » doit être un texte.');

        self::fields(['reason' => $value])->line('reason', 200);
    }

    public function testAMissingOrNullFieldReadsAsEmptyForTheDomainToJudge(): void
    {
        self::assertSame('', self::fields([])->line('reason', 200));
        self::assertSame('', self::fields(['reason' => null])->line('reason', 200));
        self::assertSame('', RequestFields::of(Request::create('/', 'POST'))->line('reason', 200));
    }

    public function testATextIsCleanedBeforeItIsMeasured(): void
    {
        self::assertSame('Soirée annulée', self::fields(['reason' => "  Soirée\u{202E}  annulée\u{0000} "])->line('reason', 14));
    }

    public function testATextAtItsLimitIsAcceptedAndOneCharacterMoreIsRefused(): void
    {
        self::assertSame(str_repeat('é', 200), self::fields(['reason' => str_repeat('é', 200)])->line('reason', 200));

        $this->expectException(Unprocessable::class);
        $this->expectExceptionMessage('Le champ « reason » dépasse 200 caractères.');
        self::fields(['reason' => str_repeat('é', 201)])->line('reason', 200);
    }

    public function testSeveralLinesKeepTheirBreaks(): void
    {
        self::assertSame("Bonne soirée\nà tous", self::fields(['body' => "Bonne soirée\r\nà tous"])->lines('body', 50));
    }

    public function testASecretIsNeverCleanedButStaysBounded(): void
    {
        self::assertSame(' 12 34 ', self::fields(['code' => ' 12 34 '])->secret('code', 20));

        $this->expectException(Unprocessable::class);
        self::fields(['code' => str_repeat('1', 21)])->secret('code', 20);
    }

    public function testAFormFieldThatIsNotValidUtf8IsRefused(): void
    {
        $this->expectException(Unprocessable::class);

        RequestFields::ofForm(Request::create('/', 'POST', ['label' => "Re\xC3u"]))->line('label', 80);
    }

    public function testItTellsWhetherAFieldWasSent(): void
    {
        $fields = self::fields(['title' => 'Soirée']);

        self::assertTrue($fields->has('title'));
        self::assertFalse($fields->has('body'));
    }
}
