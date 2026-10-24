<?php

namespace App\Messaging;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/** Qui gère quelle boîte du bureau (hiérarchie des rôles incluse). */
final class MailboxAccess
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly UserRepository $users,
    ) {
    }

    /** @return list<Mailbox> */
    public function mailboxesManagedBy(User $user): array
    {
        $roles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        return array_values(array_filter(
            Mailbox::cases(),
            static fn (Mailbox $mailbox): bool => \in_array($mailbox->role()->value, $roles, true),
        ));
    }

    /**
     * Fil direct : ses deux participants. Boîte du bureau : l'adhérent qui a écrit et les titulaires
     * actuels du rôle ; un ancien titulaire qui y a lu ou répondu n'y a plus accès.
     */
    public function canAccess(User $user, Conversation $conversation): bool
    {
        $mailbox = $conversation->getMailbox();
        if (null === $mailbox) {
            return $conversation->isParticipant($user);
        }

        return $conversation->isOpenedBy($user) || \in_array($mailbox, $this->mailboxesManagedBy($user), true);
    }

    /**
     * Qui lit un fil : ses participants (fil direct), ou l'adhérent qui a écrit et les titulaires
     * actuels du rôle (boîte du bureau).
     *
     * @return list<User>
     */
    public function readersOf(Conversation $conversation): array
    {
        $mailbox = $conversation->getMailbox();
        if (null === $mailbox) {
            return $conversation->participantUsers();
        }

        $requester = $conversation->requester();

        return [...(null !== $requester ? [$requester] : []), ...$this->holdersOf($mailbox)];
    }

    /**
     * Titulaires actuels d'une boîte. Filtrage en PHP : le club compte
     * quelques centaines de comptes et les rôles sont stockés en JSON.
     *
     * @return list<User>
     */
    public function holdersOf(Mailbox $mailbox): array
    {
        return array_values(array_filter(
            $this->users->findActive(),
            fn (User $user): bool => \in_array($mailbox, $this->mailboxesManagedBy($user), true),
        ));
    }
}
