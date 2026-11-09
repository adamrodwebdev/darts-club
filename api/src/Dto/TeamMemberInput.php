<?php

declare(strict_types=1);

namespace App\Dto;

use App\Evening\Team;
use Symfony\Component\Validator\Constraints as Assert;

/** Un membre placé dans une équipe de la soirée : accueil, bar ou cuisine. */
final class TeamMemberInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $userId = '',
        #[Assert\Choice(callback: [self::class, 'teams'])]
        public readonly string $team = '',
    ) {
    }

    /** @return list<string> */
    public static function teams(): array
    {
        return array_map(static fn (Team $team): string => $team->value, Team::cases());
    }
}
