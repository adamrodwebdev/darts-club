<?php

namespace App\Admin;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des membres par le président (et l'admin technique).
 * Règles : on ne se retire pas soi-même la présidence ni ne se suspend
 * (pas de verrouillage accidentel) ; seul un admin gère le rôle admin
 * ou modère un admin.
 */
final class MemberAdministration
{
    /** Rôles du bureau que le président attribue */
    public const BOARD_ROLES = [Role::Secretary, Role::Treasurer, Role::President];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditTrail $audit,
    ) {
    }

    /** @param list<Role> $roles nouvel ensemble de rôles du bureau du membre */
    public function changeBoardRoles(User $actor, User $member, array $roles): void
    {
        foreach ($roles as $role) {
            if (Role::Admin === $role || Role::Member === $role) {
                throw new AdministrationException('Seuls les rôles du bureau sont attribuables ici.');
            }
        }
        if (!$member->isVerified()) {
            throw new AdministrationException('Le compte doit être vérifié avant de recevoir un rôle.');
        }
        if ($member->getId()->equals($actor->getId()) && $member->hasRole(Role::President) && !\in_array(Role::President, $roles, true) && !$actor->hasRole(Role::Admin)) {
            throw new AdministrationException('Tu ne peux pas te retirer toi-même la présidence : demande à un autre président ou à l\'admin.');
        }

        $before = array_map(static fn (Role $r): string => $r->value, $member->assignedRoles());
        foreach (self::BOARD_ROLES as $role) {
            \in_array($role, $roles, true) ? $member->grantRole($role) : $member->revokeRole($role);
        }
        $after = array_map(static fn (Role $r): string => $r->value, $member->assignedRoles());

        if ($before !== $after) {
            $this->audit->record($actor, 'member.roles', $member->displayName()->value, [
                'before' => implode(',', $before),
                'after' => implode(',', $after),
            ]);
        }
        $this->em->flush();
    }

    public function suspend(User $actor, User $member, string $reason): void
    {
        if ($member->getId()->equals($actor->getId())) {
            throw new AdministrationException('Tu ne peux pas suspendre ton propre compte.');
        }
        if ($member->hasRole(Role::Admin) && !\in_array(Role::Admin, $actor->assignedRoles(), true)) {
            throw new AdministrationException('Seul un administrateur peut suspendre un administrateur.');
        }

        try {
            $member->suspend($reason);
        } catch (\InvalidArgumentException $e) {
            throw new AdministrationException($e->getMessage(), $e);
        }

        $this->audit->record($actor, 'member.suspend', $member->displayName()->value, ['reason' => $member->suspensionReason()]);
        $this->em->flush();
    }

    public function reactivate(User $actor, User $member): void
    {
        $member->reactivate();
        $this->audit->record($actor, 'member.reactivate', $member->displayName()->value);
        $this->em->flush();
    }
}
