<?php

namespace App\ClubEvent;

use App\Preference\Language;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;

/** Ce qu'une personne sans compte envoie depuis la page publique d'un événement */
final readonly class GuestRequest
{
    public function __construct(
        public DisplayName $name,
        public EmailAddress $email,
        public Attendance $answer,
        /** Langue des emails de confirmation et de suivi */
        public Language $language,
    ) {
    }
}
