<?php

namespace App\Documents;

use App\Entity\ClubEvent;
use App\EventAccounts\SignedAmount;
use App\Membership\Money;

/** Tournures reprises de la soirée dans les documents : quand, où, combien. */
final class EventPhrases
{
    /** « le samedi 14 novembre 2026, de 20 h à 0 h » */
    public static function when(ClubEvent $event): string
    {
        return 'le '.FrenchDates::day($event->getStartsAt()).', de '.FrenchDates::time($event->getStartsAt()).' à '.FrenchDates::time($event->getEndsAt());
    }

    /** Commune de la soirée (affichée au public), sinon celle du siège */
    public static function commune(ClubEvent $event): string
    {
        return $event->getCity() ?? LetterParts::SEAT_TOWN;
    }

    /** Jauge de la soirée, vide si elle n'en a pas */
    public static function attendance(ClubEvent $event): string
    {
        $capacity = $event->getCapacity();

        return null === $capacity ? '' : (string) $capacity;
    }

    /** « 30,50 € » */
    public static function money(Money $amount): string
    {
        return (string) $amount;
    }

    /** « +402,40 € », « −12,40 € » */
    public static function signed(SignedAmount $amount): string
    {
        $text = (string) Money::cents(abs($amount->cents));

        return match (true) {
            $amount->cents > 0 => '+'.$text,
            $amount->cents < 0 => '−'.$text,
            default => $text,
        };
    }
}
