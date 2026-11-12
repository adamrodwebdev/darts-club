<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dto\VideoUploadInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Un champ scalaire d'un DTO reçoit une valeur de son type ou rien : le refus a lieu avant que le
 * DTO soit construit, sans que PHP convertisse la valeur (8700.5 en 8700 avec un avertissement).
 */
final class PayloadTypesTest extends KernelTestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function mistypedSizes(): iterable
    {
        yield 'un nombre écrit en texte' => ['870000'];
        yield 'un décimal' => [8700.5];
        yield 'un booléen' => [true];
        yield 'une liste' => [[870000]];
    }

    #[DataProvider('mistypedSizes')]
    public function testAMistypedNumberIsRefusedBeforeTheDtoIsBuilt(mixed $size): void
    {
        $json = json_encode(['fileName' => 'clip.mp4', 'size' => $size, 'durationMs' => 12000, 'mediaType' => 'video/mp4'], \JSON_THROW_ON_ERROR);

        try {
            // Le contexte que #[MapRequestPayload] passe pour un corps JSON
            self::serializer()->deserialize($json, VideoUploadInput::class, 'json', ['collect_denormalization_errors' => true]);
            self::fail('La taille aurait dû être refusée');
        } catch (PartialDenormalizationException $e) {
            self::assertSame(['size'], array_map(static fn ($error): ?string => $error->getPath(), $e->getErrors()));
        }
    }

    public function testANumberOfTheRightTypeIsKept(): void
    {
        $input = self::serializer()->deserialize('{"fileName":"clip.mp4","size":870000,"durationMs":12000,"mediaType":"video/mp4"}', VideoUploadInput::class, 'json', ['collect_denormalization_errors' => true]);

        self::assertInstanceOf(VideoUploadInput::class, $input);
        self::assertSame(870000, $input->size);
    }

    private static function serializer(): SerializerInterface
    {
        $serializer = self::getContainer()->get('serializer');
        self::assertInstanceOf(SerializerInterface::class, $serializer);

        return $serializer;
    }
}
