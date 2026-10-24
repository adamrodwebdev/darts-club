<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Règles d'un nouveau mot de passe (inscription, oubli, changement), choisies par l'association :
 * 6 caractères au moins, dont un chiffre et un caractère spécial, et absent des fuites de données
 * publiques. Le front affiche les mêmes règles (web/src/models/PasswordPolicy.ts).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class PasswordPolicy extends Compound
{
    public const MIN_LENGTH = 6;

    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(min: self::MIN_LENGTH, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
            new Assert\Regex(pattern: '/\p{Nd}/u', message: 'Le mot de passe doit contenir au moins un chiffre.'),
            // Ni lettre (accents compris) ni chiffre : ponctuation, symbole ou espace
            new Assert\Regex(pattern: '/[^\p{L}\p{M}\p{Nd}]/u', message: 'Le mot de passe doit contenir au moins un caractère spécial (! ? @ # …).'),
            new Assert\NotCompromisedPassword(message: 'Ce mot de passe figure dans des fuites de données publiques : choisis-en un autre.'),
        ];
    }
}
