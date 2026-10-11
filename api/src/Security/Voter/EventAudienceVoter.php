<?php

namespace App\Security\Voter;

use App\Entity\ClubEvent;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Statistiques d'audience d'un événement : administrateur uniquement
 * (ROLE_ADMIN est au sommet de la hiérarchie : ni la présidence ni le bureau).
 *
 * @extends Voter<string, ClubEvent>
 */
final class EventAudienceVoter extends Voter
{
    public const VIEW = 'EVENT_AUDIENCE_VIEW';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof ClubEvent;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof User && $this->decisions->decide($token, ['ROLE_ADMIN']);
    }
}
