<?php

namespace App\Entity;

use App\Evening\Team;
use App\Evening\TeamAssignment;
use App\Repository\EveningTeamMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Membre d'une équipe de la soirée (accueil, bar, cuisine), placé par le bureau. */
#[ORM\Entity(repositoryClass: EveningTeamMemberRepository::class)]
#[ORM\Table(name: 'evening_team_member')]
#[ORM\UniqueConstraint(name: 'uniq_evening_team_member', columns: ['event_id', 'user_id', 'team'])]
class EveningTeamMember
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10, enumType: Team::class)]
    private Team $team;

    #[ORM\Column]
    private \DateTimeImmutable $assignedAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        TeamAssignment $assignment,
        \DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->user = $assignment->member;
        $this->team = $assignment->team;
        $this->assignedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function event(): ClubEvent
    {
        return $this->event;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function team(): Team
    {
        return $this->team;
    }

    public function assignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
