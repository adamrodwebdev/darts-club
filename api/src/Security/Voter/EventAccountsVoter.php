<?php

namespace App\Security\Voter;

use App\Entity\AccountsEntry;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
use App\Entity\Receipt;
use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Bilan des soirées (décidé avec le bureau) : tout le bureau le lit ; la secrétaire et le trésorier
 * le tiennent ; seul le trésorier rouvre un bilan clôturé. Rôles attribués, pas hérités : la
 * présidence lit sans modifier les comptes. La carte, l'encaissement (jetons) et les équipes de la
 * soirée se préparent par tout le bureau et l'administrateur.
 *
 * @extends Voter<string, ClubEvent|BarItem|AccountsEntry|Receipt|EveningTeamMember>
 */
final class EventAccountsVoter extends Voter
{
    public const VIEW = 'EVENT_ACCOUNTS_VIEW';
    public const EDIT = 'EVENT_ACCOUNTS_EDIT';
    public const REOPEN = 'EVENT_ACCOUNTS_REOPEN';
    public const EDIT_MENU = 'EVENT_ACCOUNTS_EDIT_MENU';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::REOPEN, self::EDIT_MENU], true)
            && ($subject instanceof ClubEvent || $subject instanceof BarItem || $subject instanceof AccountsEntry || $subject instanceof Receipt || $subject instanceof EveningTeamMember);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $roles = $user->assignedRoles();

        return match ($attribute) {
            self::VIEW, self::EDIT_MENU => $user->isBoardMember(),
            self::EDIT => \in_array(Role::Secretary, $roles, true) || \in_array(Role::Treasurer, $roles, true),
            default => \in_array(Role::Treasurer, $roles, true),
        };
    }
}
