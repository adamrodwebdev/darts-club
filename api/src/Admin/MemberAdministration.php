<?php

namespace App\Admin;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Gestion des membres par le président (et l'admin technique).
 * Règles : on ne se retire pas soi-même la présidence ni ne se suspend
 * (pas de verrouillage accidentel) ; seul un admin gère le rôle admin,
 * la présidence (nommer, retirer, modifier les rôles d'un président)
 * et modère un admin ou un président.
 */
final class MemberAdministration
{
    /** Rôles du bureau que le président attribue */
    public const BOARD_ROLES = [Role::Secretary, Role::Treasurer, Role::President];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditTrail $audit,
        private readonly ClockInterface $clock,
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
        $this->assertPresidencyRules($actor, $member, \in_array(Role::President, $roles, true));

        $before = array_map(static fn (Role $r): string => $r->value, $member->assignedRoles());
        foreach (self::BOARD_ROLES as $role) {
            \in_array($role, $roles, true) ? $member->grantRole($role) : $member->revokeRole($role);
        }
        $after = array_map(static fn (Role $r): string => $r->value, $member->assignedRoles());

        if ($before !== $after) {
            $this->audit->record($actor, new AuditEntry('member.roles', $member->displayName()->value, [
                'before' => implode(',', $before),
                'after' => implode(',', $after),
            ]));
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
        if ($member->hasRole(Role::President) && !$actor->hasRole(Role::Admin)) {
            throw new AdministrationException('Seul l\'administrateur peut suspendre un président.');
        }

        try {
            $member->suspend($reason, $this->clock->now());
        } catch (\InvalidArgumentException $e) {
            throw new AdministrationException($e->getMessage(), $e);
        }

        $this->audit->record($actor, new AuditEntry('member.suspend', $member->displayName()->value, ['reason' => $member->suspensionReason()]));
        $this->em->flush();
    }

    /**
     * La présidence vient d'un vote : seul l'administrateur la confie, la retire ou touche aux rôles
     * d'un président. Un compte de président volé ne peut ainsi ni écarter l'autre président, ni
     * nommer un complice. Un président garde la main sur ses propres rôles du bureau, sauf la présidence.
     */
    private function assertPresidencyRules(User $actor, User $member, bool $staysPresident): void
    {
        if ($actor->hasRole(Role::Admin)) {
            return;
        }

        $isPresident = $member->hasRole(Role::President);
        if ($member->getId()->equals($actor->getId())) {
            if ($isPresident && !$staysPresident) {
                throw new AdministrationException('Tu ne peux pas te retirer toi-même la présidence : demande à l\'administrateur.');
            }

            return;
        }
        if ($isPresident || $staysPresident) {
            throw new AdministrationException('Seul l\'administrateur nomme un président ou modifie ses rôles.');
        }
    }

    public function reactivate(User $actor, User $member): void
    {
        if ($member->hasRole(Role::Admin) && !\in_array(Role::Admin, $actor->assignedRoles(), true)) {
            throw new AdministrationException('Seul un administrateur peut réactiver un administrateur.');
        }
        if ($member->hasRole(Role::President) && !$actor->hasRole(Role::Admin)) {
            throw new AdministrationException('Seul l\'administrateur peut réactiver un président.');
        }

        $member->reactivate();
        $this->audit->record($actor, new AuditEntry('member.reactivate', $member->displayName()->value));
        $this->em->flush();
    }
}
