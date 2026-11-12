<?php

declare(strict_types=1);

namespace App\Evening;

use App\Translation\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * Vente préparée à l'accueil. Son identifiant est tiré sur l'appareil : renvoyée après une coupure
 * de réseau, la même vente n'est jamais comptée deux fois.
 */
final readonly class NewTokenSale
{
    public const LINES_MAX = 12;

    /** @var list<SaleLine> */
    public array $lines;

    /** @param list<SaleLine> $lines */
    public function __construct(
        public Uuid $id,
        public PaymentMethod $method,
        array $lines,
    ) {
        if ([] === $lines || \count($lines) > self::LINES_MAX) {
            throw new InvalidValue('Une vente compte de 1 à %max% lignes.', ['%max%' => self::LINES_MAX]);
        }
        $names = array_map(static fn (SaleLine $line): string => mb_strtolower($line->token->name), $lines);
        if (\count(array_unique($names)) !== \count($names)) {
            throw new InvalidValue('Un même jeton apparaît deux fois dans la vente.');
        }
        $this->lines = $lines;
    }
}
