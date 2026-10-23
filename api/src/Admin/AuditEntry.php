<?php

namespace App\Admin;

/** Ligne du journal d'administration : action (« member.roles »…), cible lisible et détails */
final readonly class AuditEntry
{
    /** @param array<string, scalar|null> $details */
    public function __construct(
        public string $action,
        public ?string $target = null,
        public array $details = [],
    ) {
    }
}
