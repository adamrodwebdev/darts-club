<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
use App\Entity\User;
use App\Evening\Team;
use App\Evening\TeamAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EveningTeamMember>
 */
class EveningTeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EveningTeamMember::class);
    }

    /** @return list<EveningTeamMember> */
    public function forEvent(ClubEvent $event): array
    {
        return $this->findBy(['event' => $event], ['assignedAt' => 'ASC']);
    }

    /** @return list<Team> les équipes de ce membre pour cette soirée */
    public function teamsOf(ClubEvent $event, User $user): array
    {
        return array_map(static fn (EveningTeamMember $member): Team => $member->team(), $this->findBy(['event' => $event, 'user' => $user]));
    }

    public function findAssignment(ClubEvent $event, TeamAssignment $assignment): ?EveningTeamMember
    {
        return $this->findOneBy(['event' => $event, 'user' => $assignment->member, 'team' => $assignment->team]);
    }
}
