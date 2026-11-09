<?php

declare(strict_types=1);

namespace App\Text;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Nettoie chaque texte d'un corps de requête avant qu'il remplisse un DTO de App\Dto
 * (#[MapRequestPayload]) : aucun DTO ne peut l'oublier. Un champ #[Multiline] garde ses retours
 * à la ligne, un champ #[Secret] n'est jamais touché, les listes sont nettoyées élément par
 * élément. Les contraintes du DTO jugent ensuite le texte propre (longueur, format).
 */
final class CleanTextDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /** Drapeau de contexte : ce corps a déjà été nettoyé pour cette classe */
    private const CLEANED = 'app_text_cleaned';

    private const PAYLOADS = 'App\\Dto\\';

    /** @var array<string, array<string, TextMode>> mode de chaque champ, par classe */
    private array $modes = [];

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $modes = $this->modesOf($type);
        $clean = [];
        foreach (\is_array($data) ? $data : [] as $key => $value) {
            $clean[$key] = self::clean($value, $modes[$key] ?? TextMode::Line);
        }

        return $this->denormalizer->denormalize($clean, $type, $format, [self::CLEANED => $type] + $context);
    }

    /** @param array<string, mixed> $context */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \is_array($data) && str_starts_with($type, self::PAYLOADS) && ($context[self::CLEANED] ?? null) !== $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }

    private static function clean(mixed $value, TextMode $mode): mixed
    {
        if (\is_string($value)) {
            return $mode->clean($value);
        }
        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::clean($item, $mode), $value);
        }

        return $value;
    }

    /** @return array<string, TextMode> */
    private function modesOf(string $type): array
    {
        if (!isset($this->modes[$type])) {
            $modes = [];
            if (class_exists($type)) {
                foreach ((new \ReflectionClass($type))->getProperties() as $property) {
                    $modes[$property->getName()] = match (true) {
                        [] !== $property->getAttributes(Secret::class) => TextMode::Secret,
                        [] !== $property->getAttributes(Multiline::class) => TextMode::Lines,
                        default => TextMode::Line,
                    };
                }
            }
            $this->modes[$type] = $modes;
        }

        return $this->modes[$type];
    }
}
