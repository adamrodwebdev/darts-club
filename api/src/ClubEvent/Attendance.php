<?php

namespace App\ClubEvent;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Réponse à un événement : seul « je viens » occupe une place. */
enum Attendance: string implements TranslatableInterface
{
    case Going = 'je-viens';
    case Maybe = 'pas-sur';

    public function takesASeat(): bool
    {
        return self::Going === $this;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('attendance.'.str_replace('-', '_', $this->value), [], 'enums', $locale);
    }
}
