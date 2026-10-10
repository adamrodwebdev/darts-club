<?php

namespace App\Security\Voter;

use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\EventPost;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Organisation des événements : secrétaire (et président / admin par hiérarchie).
 * Photos et posts : supprimables par leur auteur ou un organisateur.
 *
 * @extends Voter<string, mixed>
 */
final class ClubEventVoter extends Voter
{
    public const MANAGE = 'CLUB_EVENT_MANAGE';
    public const DELETE_CONTENT = 'CLUB_EVENT_DELETE_CONTENT';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (self::MANAGE === $attribute && (null === $subject || $subject instanceof ClubEvent))
            || (self::DELETE_CONTENT === $attribute && ($subject instanceof EventPhoto || $subject instanceof EventPost));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $organizer = $this->decisions->decide($token, ['ROLE_SECRETARY']);

        return match (true) {
            self::MANAGE === $attribute => $organizer,
            $subject instanceof EventPhoto => $organizer || $subject->isUploadedBy($user),
            $subject instanceof EventPost => $organizer || $subject->isWrittenBy($user),
            default => false,
        };
    }
}
