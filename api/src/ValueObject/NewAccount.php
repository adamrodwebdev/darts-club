<?php

namespace App\ValueObject;

use App\Preference\Language;

/** Ce qu'une personne fournit pour créer son compte (le mot de passe est haché à part). */
final readonly class NewAccount
{
    public function __construct(
        public EmailAddress $email,
        public ?PhoneNumber $phone = null,
        public Language $language = Language::French,
    ) {
    }
}
