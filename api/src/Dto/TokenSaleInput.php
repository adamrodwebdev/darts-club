<?php

namespace App\Dto;

use App\Evening\NewTokenSale;
use App\Evening\PaymentMethod;
use App\Evening\SaleOrder;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Vente de l'accueil : identifiant tiré sur l'appareil, moyen de paiement, lignes
 * [{ name, count }]. Les valeurs ne viennent jamais de l'appareil.
 */
final class TokenSaleInput
{
    /** @param list<mixed> $lines */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $id = '',
        #[Assert\Choice(callback: [self::class, 'methods'])]
        public readonly string $method = '',
        #[Assert\Count(min: 1, max: NewTokenSale::LINES_MAX)]
        public readonly array $lines = [],
    ) {
    }

    /** @return list<string> */
    public static function methods(): array
    {
        return array_map(static fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases());
    }

    public function toOrder(): SaleOrder
    {
        $counts = [];
        foreach ($this->lines as $line) {
            $line = \is_array($line) ? $line : [];
            $name = \is_string($line['name'] ?? null) ? $line['name'] : '';
            // Deux lignes du même jeton : additionnées
            $counts[$name] = ($counts[$name] ?? 0) + (\is_int($line['count'] ?? null) ? $line['count'] : 0);
        }

        return new SaleOrder(Uuid::fromString($this->id), PaymentMethod::from($this->method), $counts);
    }
}
