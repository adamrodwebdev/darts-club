<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Règle du 2026-11-09 : chaque texte reçu a une longueur maximale vérifiée dans le code, chaque
 * liste un nombre maximal d'éléments. Ce test lit chaque DTO de requête (src/Dto) : un nouveau
 * champ sans borne le fait échouer.
 */
final class PayloadLimitsTest extends TestCase
{
    /** Contraintes qui bornent déjà un texte : un choix fermé, une valeur imposée, un identifiant, une date */
    private const BOUNDED_FORMATS = [Assert\Choice::class, Assert\EqualTo::class, Assert\Uuid::class, Assert\Date::class];

    /** @return iterable<string, array{\ReflectionProperty}> */
    public static function textAndListFields(): iterable
    {
        foreach (glob(__DIR__.'/../../src/Dto/*.php') ?: [] as $file) {
            $class = 'App\\Dto\\'.basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType && \in_array($type->getName(), ['string', 'array'], true)) {
                    yield $class.'::$'.$property->getName() => [$property];
                }
            }
        }
    }

    #[DataProvider('textAndListFields')]
    public function testEveryTextAndListOfARequestHasAMaximum(\ReflectionProperty $property): void
    {
        $type = $property->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);

        if ('array' === $type->getName()) {
            self::assertTrue($this->hasMaximum($property, Assert\Count::class) || $this->isClosedCollection($property), 'Une liste reçue a besoin de #[Assert\Count(max: …)] (ou d\'une Collection fermée)');

            return;
        }
        $bounded = array_filter(self::BOUNDED_FORMATS, static fn (string $constraint): bool => [] !== $property->getAttributes($constraint));
        self::assertTrue([] !== $bounded || $this->hasMaximum($property, Assert\Length::class), 'Un texte reçu a besoin de #[Assert\Length(max: …)] (ou d\'un format fermé : Choice, Uuid, Date)');
    }

    /**
     * La contrainte est posée avec un maximum, directement ou dans une contrainte composée
     * (PasswordPolicy contient Length(max: 4096)).
     *
     * @param class-string<Assert\Length|Assert\Count> $constraint
     */
    private function hasMaximum(\ReflectionProperty $property, string $constraint): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            $parts = $instance instanceof Assert\Compound ? $instance->constraints : [$instance];
            foreach ($parts as $part) {
                if ($part instanceof $constraint && null !== $part->max) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Une Collection sans champ supplémentaire a un nombre d'éléments fixé */
    private function isClosedCollection(\ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes(Assert\Collection::class) as $attribute) {
            if (!$attribute->newInstance()->allowExtraFields) {
                return true;
            }
        }

        return false;
    }
}
