<?php

namespace App\Dto;

use App\Preference\Language;
use App\Preference\Theme;
use Symfony\Component\Validator\Constraints as Assert;

/** Préférences d'affichage envoyées par l'interface (langue et thème). */
final class DisplayPreferencesInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'languages'])]
        public readonly string $language = '',

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'themes'])]
        public readonly string $theme = '',
    ) {
    }

    /** @return list<string> */
    public static function languages(): array
    {
        return array_map(static fn (Language $l): string => $l->value, Language::cases());
    }

    /** @return list<string> */
    public static function themes(): array
    {
        return array_map(static fn (Theme $t): string => $t->value, Theme::cases());
    }
}
