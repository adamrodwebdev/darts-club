<?php

namespace App\Documents;

/** Formulaire ou service officiel lié à une démarche (Cerfa, SACEM…). */
final readonly class OfficialLink
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
    }
}
