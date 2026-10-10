<?php

namespace App\Membership;

/** Saison sportive du club : du 1er septembre au 31 août (« 2026-2027 »). */
final readonly class Season implements \Stringable
{
    private const START_MONTH = 9;
    /** Délai de paiement après l'ouverture de la saison */
    private const PAYMENT_DELAY = 'P1M';

    private function __construct(
        public int $startYear,
    ) {
    }

    public static function containing(\DateTimeInterface $date): self
    {
        $year = (int) $date->format('Y');

        return new self((int) $date->format('n') >= self::START_MONTH ? $year : $year - 1);
    }

    public static function fromLabel(string $label): self
    {
        if (1 !== preg_match('/^(\d{4})-(\d{4})$/', $label, $m) || (int) $m[2] !== (int) $m[1] + 1) {
            throw new \InvalidArgumentException(\sprintf('Saison invalide : "%s" (format attendu : 2026-2027).', $label));
        }

        return new self((int) $m[1]);
    }

    public function label(): string
    {
        return \sprintf('%d-%d', $this->startYear, $this->startYear + 1);
    }

    public function startsAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%d-%02d-01 00:00:00', $this->startYear, self::START_MONTH));
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->next()->startsAt()->modify('-1 second');
    }

    /** Date limite de paiement de la cotisation */
    public function dueDate(): \DateTimeImmutable
    {
        return $this->startsAt()->add(new \DateInterval(self::PAYMENT_DELAY));
    }

    public function next(): self
    {
        return new self($this->startYear + 1);
    }

    public function previous(): self
    {
        return new self($this->startYear - 1);
    }

    public function equals(self $other): bool
    {
        return $this->startYear === $other->startYear;
    }

    public function __toString(): string
    {
        return $this->label();
    }
}
