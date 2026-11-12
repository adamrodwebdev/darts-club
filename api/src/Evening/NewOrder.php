<?php

declare(strict_types=1);

namespace App\Evening;

use App\Translation\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * Commande préparée au bar. Son identifiant est tiré sur l'appareil : renvoyée après une coupure,
 * la même commande n'est jamais prise deux fois. Le repère (« table 4 », « veste rouge ») aide à
 * retrouver le client.
 */
final readonly class NewOrder
{
    public const LINES_MAX = 30;
    public const NOTE_MAX = 40;

    public ?string $note;

    /** @param list<OrderRequest> $lines */
    public function __construct(
        public Uuid $id,
        public array $lines,
        ?string $note = null,
    ) {
        if ([] === $lines || \count($lines) > self::LINES_MAX) {
            throw new InvalidValue('Une commande compte de 1 à %max% lignes.', ['%max%' => self::LINES_MAX]);
        }
        $note = null === $note ? '' : trim($note);
        if (mb_strlen($note) > self::NOTE_MAX) {
            throw new InvalidValue('Le repère fait %max% caractères au plus.', ['%max%' => self::NOTE_MAX]);
        }
        $this->note = '' === $note ? null : $note;
    }
}
