<?php

namespace App\Notification;

use Symfony\Component\Uid\Uuid;

/** Destinataires : une sélection d'adhérents ou tous les adhérents vérifiés. */
final readonly class Recipients
{
    /** @param list<string> $userIds */
    private function __construct(
        public array $userIds,
        public bool $allMembers,
    ) {
    }

    public static function users(Uuid ...$ids): self
    {
        if ([] === $ids) {
            throw new \InvalidArgumentException('Au moins un destinataire est requis.');
        }

        return new self(array_values(array_unique(array_map(static fn (Uuid $id): string => $id->toRfc4122(), $ids))), false);
    }

    public static function allMembers(): self
    {
        return new self([], true);
    }
}
