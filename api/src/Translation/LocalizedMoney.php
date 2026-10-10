<?php

namespace App\Translation;

use App\Membership\Money;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Montant en euros au format de la langue du lecteur (« 30,00 € », « €30.00 »). */
final readonly class LocalizedMoney implements TranslatableInterface
{
    public function __construct(
        private Money $amount,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $formatter = new \NumberFormatter('en' === ($locale ?? $translator->getLocale()) ? 'en_GB' : 'fr_FR', \NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency((float) $this->amount->toDecimal(), 'EUR');
    }
}
