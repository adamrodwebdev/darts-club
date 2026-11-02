<?php

namespace App\Security\Voter;

use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EveningOrder;
use App\Entity\TokenSale;
use App\Entity\User;
use App\Evening\ServiceWindow;
use App\Evening\Team;
use App\Repository\EveningTeamMemberRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Écrans d'une soirée : chaque équipe (accueil, bar, cuisine) ouvre le sien le jour de la soirée
 * seulement, et jamais pour une soirée annulée ; le bureau et l'administrateur ont accès à tout,
 * à tout moment (préparer, vérifier, dépanner).
 *
 * @extends Voter<string, ClubEvent|TokenSale|EveningOrder|BarItem>
 */
final class EveningServiceVoter extends Voter
{
    public const ENTRANCE = 'EVENING_ENTRANCE';
    public const BAR = 'EVENING_BAR';
    public const KITCHEN = 'EVENING_KITCHEN';
    /** Recevoir le temps réel de la soirée : n'importe quelle équipe, ou le bureau */
    public const WATCH = 'EVENING_WATCH';
    /** Servir : le bar ou la cuisine (annuler une commande qui attend, déclarer un article épuisé) */
    public const SERVE = 'EVENING_SERVE';

    private const TEAMS = [self::ENTRANCE => Team::Welcome, self::BAR => Team::Bar, self::KITCHEN => Team::Kitchen];

    public function __construct(
        private readonly EveningTeamMemberRepository $teams,
        private readonly ClockInterface $clock,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (isset(self::TEAMS[$attribute]) || self::WATCH === $attribute || self::SERVE === $attribute)
            && ($subject instanceof ClubEvent || $subject instanceof TokenSale || $subject instanceof EveningOrder || $subject instanceof BarItem);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($user->isBoardMember()) {
            return true;
        }

        $event = match (true) {
            $subject instanceof TokenSale, $subject instanceof EveningOrder => $subject->event(),
            $subject instanceof BarItem => $subject->accounts()->event(),
            default => $subject,
        };
        if ($event->isCancelled() || !ServiceWindow::of($event)->isOpenAt($this->clock->now())) {
            return false;
        }
        $teams = $this->teams->teamsOf($event, $user);

        return match ($attribute) {
            self::WATCH => [] !== $teams,
            self::SERVE => \in_array(Team::Bar, $teams, true) || \in_array(Team::Kitchen, $teams, true),
            default => \in_array(self::TEAMS[$attribute] ?? null, $teams, true),
        };
    }
}
