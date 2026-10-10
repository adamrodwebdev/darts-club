<?php

namespace App\Membership;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\MembershipRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Calcule les badges d'un adhérent. Les cotisants de la saison sont chargés
 * une seule fois par requête (évite une requête par adhérent affiché).
 */
final class MemberBadges implements ResetInterface
{
    /** @var array<string, true>|null */
    private ?array $paidUp = null;

    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<Badge> fonctions d'abord, puis cotisant */
    public function of(User $user): array
    {
        $badges = [];
        foreach ([Role::President, Role::Treasurer, Role::Secretary] as $role) {
            // Rôles attribués directement : un admin n'est pas « président » par hiérarchie
            if (\in_array($role->value, $user->getRoles(), true)) {
                $badges[] = Badge::forRole($role);
            }
        }

        $this->paidUp ??= $this->memberships->paidUpUserIds(Season::containing($this->clock->now()));
        if (isset($this->paidUp[$user->getId()->toRfc4122()])) {
            $badges[] = Badge::PaidUp;
        }

        return array_values(array_filter($badges));
    }

    /** @return list<array{id: string, label: string}> */
    public function present(User $user): array
    {
        return array_map(static fn (Badge $b): array => ['id' => $b->value, 'label' => $b->label()], $this->of($user));
    }

    public function reset(): void
    {
        $this->paidUp = null;
    }
}
