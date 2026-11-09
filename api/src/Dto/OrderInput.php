<?php

declare(strict_types=1);

namespace App\Dto;

use App\Evening\NewOrder;
use App\Evening\OrderRequest;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande du bar : identifiant tiré sur l'appareil, repère facultatif, lignes
 * [{ itemId, format, count, garnishes }]. Les prix ne viennent jamais de l'appareil.
 */
final class OrderInput
{
    /** @param list<mixed> $lines */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $id = '',
        #[Assert\Count(min: 1, max: NewOrder::LINES_MAX)]
        public readonly array $lines = [],
        public readonly ?string $note = null,
    ) {
    }

    public function toOrder(): NewOrder
    {
        $lines = array_map(static function (mixed $line): OrderRequest {
            $line = \is_array($line) ? $line : [];
            $garnishes = \is_array($line['garnishes'] ?? null) ? array_values(array_filter($line['garnishes'], 'is_string')) : [];

            return new OrderRequest(
                itemId: \is_string($line['itemId'] ?? null) ? $line['itemId'] : '',
                format: \is_string($line['format'] ?? null) ? $line['format'] : '',
                count: \is_int($line['count'] ?? null) ? $line['count'] : 0,
                garnishes: $garnishes,
            );
        }, $this->lines);

        return new NewOrder(Uuid::fromString($this->id), $lines, $this->note);
    }
}
