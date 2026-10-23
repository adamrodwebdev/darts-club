<?php

namespace App\Admin;

/** Destinataires d'un message du président : tous les adhérents, ou une liste choisie */
final readonly class BroadcastAudience
{
    /** @param list<string>|null $userIds null = tous les adhérents */
    private function __construct(
        public ?array $userIds,
    ) {
    }

    public static function allMembers(): self
    {
        return new self(null);
    }

    /** @param list<string> $userIds */
    public static function members(array $userIds): self
    {
        return new self($userIds);
    }

    public function isEveryone(): bool
    {
        return null === $this->userIds;
    }
}
