<?php

namespace App\Membership;

use App\Translation\InvalidValue;

/** Montant en euros, stocké en centimes (jamais de flottants pour la compta). */
final readonly class Money implements \Stringable
{
    private function __construct(
        public int $cents,
    ) {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Un montant ne peut pas être négatif.');
        }
    }

    public static function cents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** « 30 », « 30.5 », « 30,50 » → 3050 centimes */
    public static function fromDecimal(string $amount): self
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($amount));
        if (1 !== preg_match('/^(\d{1,6})(?:\.(\d{1,2}))?$/', $normalized, $m)) {
            throw new InvalidValue('Montant invalide : « %amount% ».', ['%amount%' => $amount]);
        }

        return new self((int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0'));
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /** Prix unitaire × quantité */
    public function times(int $quantity): self
    {
        return new self($this->cents * $quantity);
    }

    /** Reste à payer, jamais négatif */
    public function remainingAfter(self $paid): self
    {
        return new self(max(0, $this->cents - $paid->cents));
    }

    public function isZero(): bool
    {
        return 0 === $this->cents;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    /** « 30.50 » (format d'échange API / CSV) */
    public function toDecimal(): string
    {
        return \sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }

    /** « 30,50 € » */
    public function __toString(): string
    {
        return number_format($this->cents / 100, 2, ',', "\u{202F}")."\u{00A0}€";
    }
}
