<?php

namespace App\Admin;

use App\Entity\User;
use App\Message\SendPasswordResetEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Lien « nouveau mot de passe » envoyé par l'administrateur à un adhérent qui a oublié le sien :
 * le même email que « mot de passe oublié », à l'adresse de l'adhérent seulement. L'administrateur
 * ne voit jamais le lien ; le mot de passe actuel reste valable tant que le lien n'a pas servi.
 */
final class MemberPasswordLinks
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly AuditTrail $audit,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function send(User $actor, User $member): void
    {
        if ($member->isSuspended()) {
            throw new AdministrationException('Compte suspendu : réactive-le avant d\'envoyer un lien.');
        }

        $this->audit->record($actor, new AuditEntry('member.password_link', $member->displayName()->value));
        $this->em->flush();
        $this->bus->dispatch(new SendPasswordResetEmail($member->getId()->toRfc4122(), byAdministrator: true));
    }
}
