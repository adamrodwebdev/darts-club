<?php

namespace App\Security\Voter;

use App\Entity\ClubEvent;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Documents des soirées et fiche de l'association : tout le bureau les lit ; le secrétariat (et la
 * présidence par hiérarchie) les prépare.
 *
 * @extends Voter<string, ClubEvent|null>
 */
final class EventDocumentsVoter extends Voter
{
    public const VIEW = 'EVENT_DOCUMENTS_VIEW';
    public const EDIT = 'EVENT_DOCUMENTS_EDIT';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true) && (null === $subject || $subject instanceof ClubEvent);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return self::VIEW === $attribute ? $user->isBoardMember() : $this->decisions->decide($token, ['ROLE_SECRETARY']);
    }
}
