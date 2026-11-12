<?php

declare(strict_types=1);

namespace App\EventAccounts;

use App\Translation\InvalidValue;

/**
 * Contenant acheté au fournisseur et ce qu'il contient, dans l'unité de stock de l'article :
 * fût de 30 L = 3 000 cl, pack de 24 bouteilles = 24, carton de 50 hot dogs = 50. Sans contenant
 * (achat à l'unité), la taille vaut 1.
 */
final readonly class ContainerSpec
{
    public const NAME_MAX = 40;
    public const SIZE_MAX = 100_000;

    public ?string $name;

    public function __construct(
        ?string $name,
        public int $size = 1,
    ) {
        $name = null === $name ? null : trim($name);
        if (null !== $name && mb_strlen($name) > self::NAME_MAX) {
            throw new InvalidValue('Le contenant fait %max% caractères au plus.', ['%max%' => self::NAME_MAX]);
        }
        if ($size < 1 || $size > self::SIZE_MAX) {
            throw new InvalidValue('Contenance invalide : de 1 à %max%.', ['%max%' => self::SIZE_MAX]);
        }
        $this->name = '' === $name ? null : $name;
    }
}
