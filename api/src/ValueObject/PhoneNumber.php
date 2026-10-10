<?php

namespace App\ValueObject;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** Numéro de téléphone stocké au format E.164 (+33612345678). */
final readonly class PhoneNumber implements \Stringable
{
    private function __construct(
        public string $e164,
    ) {
    }

    /** Saisie libre (« 06 12 34 56 78 », « +32 … »), région par défaut FR. */
    public static function fromInput(string $input, string $defaultRegion = 'FR'): self
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($input, $defaultRegion);
        } catch (NumberParseException $e) {
            throw new \InvalidArgumentException(\sprintf('Numéro de téléphone invalide : "%s".', $input), previous: $e);
        }

        if (!$util->isValidNumber($number)) {
            throw new \InvalidArgumentException(\sprintf('Numéro de téléphone invalide : "%s".', $input));
        }

        return new self($util->format($number, PhoneNumberFormat::E164));
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
