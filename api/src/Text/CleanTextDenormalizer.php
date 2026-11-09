<?php

declare(strict_types=1);

namespace App\Text;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Porte des corps de requête : chaque texte est nettoyé avant qu'il remplisse un objet de
 * App\Dto (#[MapRequestPayload]), repris de l’espace client (PR #39), et aucun ne peut l'oublier. Un champ #[Multiline]
 * garde ses retours à la ligne, un champ #[Secret] n'est jamais touché ; les listes (lignes d'un
 * devis, points à cocher) et les objets imbriqués (adresses) sont nettoyés élément par élément.
 * Les contraintes de l'objet jugent ensuite le texte propre (longueur, format).
 *
 * Un champ scalaire (int, float, bool, string) qui reçoit une valeur d'un autre type est refusé
 * ici, avec la violation que donnerait le sérialiseur (« Cette valeur doit être de type int. ») :
 * sans cela, le sérialiseur construit quand même l'objet avec la valeur reçue, et PHP la convertit
 * (« 870000 » devient 870000, 8700.5 devient 8700 avec un avertissement de dépréciation).
 */
final class CleanTextDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /** Drapeau de contexte : ce corps a déjà été nettoyé pour cette classe */
    private const CLEANED = 'app_text_cleaned';

    private const PAYLOADS = 'App\\Dto\\';

    /** Types scalaires vérifiés ici ; les autres (listes, objets) restent au sérialiseur et aux contraintes */
    private const SCALARS = ['int', 'float', 'bool', 'string'];

    /** @var array<string, array<string, TextMode>> mode de chaque champ, par classe */
    private array $modes = [];

    /** @var array<string, array<string, \ReflectionNamedType>> type scalaire de chaque champ, par classe */
    private array $scalars = [];

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $this->describe($type);
        $clean = [];
        foreach (\is_array($data) ? $data : [] as $key => $value) {
            $scalar = $this->scalars[$type][$key] ?? null;
            if (null !== $scalar && !self::fits($value, $scalar)) {
                $context = self::refuse($value, $scalar, (string) $key, $context);
                continue;
            }
            $clean[$key] = self::clean($value, $this->modes[$type][$key] ?? TextMode::Line);
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

    /** Une valeur du type déclaré, sans conversion (un entier convient à un nombre décimal) */
    private static function fits(mixed $value, \ReflectionNamedType $type): bool
    {
        return match ($type->getName()) {
            'int' => \is_int($value),
            'float' => \is_int($value) || \is_float($value),
            'bool' => \is_bool($value),
            default => \is_string($value),
        } || (null === $value && $type->allowsNull());
    }

    /**
     * Le refus rejoint ceux du sérialiseur quand il les rassemble (#[MapRequestPayload] : une
     * réponse 422 qui nomme le champ), sinon il est levé ; le champ refusé ne va pas plus loin.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function refuse(mixed $value, \ReflectionNamedType $type, string $key, array $context): array
    {
        $parent = $context['deserialization_path'] ?? null;
        $refusal = NotNormalizableValueException::createForUnexpectedDataType(
            \sprintf('The type of the "%s" attribute must be "%s" ("%s" given).', $key, $type->getName(), get_debug_type($value)),
            $value,
            [$type->getName()],
            \is_string($parent) && '' !== $parent ? $parent.'.'.$key : $key,
            true,
        );
        $collected = $context['not_normalizable_value_exceptions'] ?? null;
        if (!\is_array($collected)) {
            throw $refusal;
        }
        $collected[] = $refusal;
        // Le sérialiseur tient cette liste par référence : la liste complétée lui parvient
        $context['not_normalizable_value_exceptions'] = $collected;

        return $context;
    }

    /** Mode de nettoyage et type scalaire de chaque champ de la classe, lus une fois */
    private function describe(string $type): void
    {
        if (isset($this->modes[$type])) {
            return;
        }
        $modes = [];
        $scalars = [];
        if (class_exists($type)) {
            foreach ((new \ReflectionClass($type))->getProperties() as $property) {
                $modes[$property->getName()] = match (true) {
                    [] !== $property->getAttributes(Secret::class) => TextMode::Secret,
                    [] !== $property->getAttributes(Multiline::class) => TextMode::Lines,
                    default => TextMode::Line,
                };
                $declared = $property->getType();
                if ($declared instanceof \ReflectionNamedType && \in_array($declared->getName(), self::SCALARS, true)) {
                    $scalars[$property->getName()] = $declared;
                }
            }
        }
        $this->modes[$type] = $modes;
        $this->scalars[$type] = $scalars;
    }
}
