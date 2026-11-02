<?php

namespace App\Evening;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\ClubEvent\ClubTime;
use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
use App\Entity\User;
use App\Repository\EveningTeamMemberRepository;
use App\Translation\InvalidValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Équipes de la soirée (accueil, bar, cuisine) : le bureau y place des membres validés, chaque
 * changement au journal. Leurs écrans s'ouvriront le jour de la soirée (étape 13c).
 */
final class EveningTeams
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly EveningTeamMemberRepository $members,
        private readonly AuditTrail $audit,
    ) {
    }

    public function assign(ClubEvent $event, TeamAssignment $assignment, User $actor): void
    {
        if (!$assignment->member->isApproved() || $assignment->member->isSuspended()) {
            throw new InvalidValue('Ce membre n’est pas encore validé.');
        }
        if (null !== $this->members->findAssignment($event, $assignment)) {
            return;
        }

        $this->em->persist(new EveningTeamMember($event, $assignment, $this->clock->now()));
        $this->audit->record($actor, self::entry('team.assign', $event, $assignment));
        $this->em->flush();
    }

    public function remove(EveningTeamMember $member, User $actor): void
    {
        $this->em->remove($member);
        $this->audit->record($actor, self::entry('team.remove', $member->event(), new TeamAssignment($member->user(), $member->team())));
        $this->em->flush();
    }

    private static function entry(string $action, ClubEvent $event, TeamAssignment $assignment): AuditEntry
    {
        return new AuditEntry($action, $event->getTitle().' · '.ClubTime::formatDay($event->getStartsAt()), [
            'team' => $assignment->team->value,
            'member' => $assignment->member->displayName()->value,
        ]);
    }
}
