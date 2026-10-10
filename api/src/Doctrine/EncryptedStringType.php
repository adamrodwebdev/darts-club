<?php

namespace App\Doctrine;

use App\Security\Crypto\SecretCipher;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Colonne chiffrée de façon transparente : l'entité manipule le clair,
 * la base ne voit que le chiffré. Le chiffreur est injecté au démarrage
 * du noyau (Kernel::boot), les types Doctrine n'ayant pas d'injection.
 */
final class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    private static ?SecretCipher $cipher = null;

    public static function useCipher(SecretCipher $cipher): void
    {
        self::$cipher = $cipher;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Valeur chiffrée attendue sous forme de chaîne.');
        }

        return self::cipher()->encrypt($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return \is_string($value) ? self::cipher()->decrypt($value) : null;
    }

    private static function cipher(): SecretCipher
    {
        return self::$cipher ?? throw new \LogicException('SecretCipher non initialisé.');
    }
}
