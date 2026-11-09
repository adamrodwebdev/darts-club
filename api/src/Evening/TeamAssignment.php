<?php

declare(strict_types=1);

namespace App\Evening;

use App\Entity\User;

/** Un membre placé dans une équipe de la soirée */
final readonly class TeamAssignment
{
    public function __construct(
        public User $member,
        public Team $team,
    ) {
    }
}
