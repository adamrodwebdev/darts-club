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

    public function canAccess(User $user, Conversation $conversation): bool
    {
        $mailbox = $conversation->getMailbox();

        return $conversation->isParticipant($user)
            || (null !== $mailbox && \in_array($mailbox, $this->mailboxesManagedBy($user), true));
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
            $this->users->findBy(['verified' => true]),
            fn (User $user): bool => \in_array($mailbox, $this->mailboxesManagedBy($user), true),
        ));
    }
}
