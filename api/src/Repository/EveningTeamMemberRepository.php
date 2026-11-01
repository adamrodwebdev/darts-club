<?php

namespace App\Repository;

use App\Entity\ClubEvent;
use App\Entity\EveningTeamMember;
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

    public function findAssignment(ClubEvent $event, TeamAssignment $assignment): ?EveningTeamMember
    {
        return $this->findOneBy(['event' => $event, 'user' => $assignment->member, 'team' => $assignment->team]);
    }
}
