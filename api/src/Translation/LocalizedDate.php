<?php

namespace App\Translation;

use App\ClubEvent\ClubTime;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Date affichée à l'heure du club, dans la langue du lecteur. */
final readonly class LocalizedDate implements TranslatableInterface
{
    public function __construct(
        private \DateTimeImmutable $date,
        /** true : « Vendredi 16 octobre à 20:00 » ; false : « 16/10/2026 » */
        private bool $withTime = true,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $locale ??= $translator->getLocale();

        return $this->withTime ? ucfirst(ClubTime::format($this->date, $locale)) : ClubTime::formatDay($this->date, $locale);
    }
}
