<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Translation\Unprocessable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Nouveau mot de passe depuis le lien reçu par email. Le lien prouve la possession de l'adresse :
 * un compte jamais confirmé (peut-être inscrit par quelqu'un d'autre) devient celui de son titulaire.
 */
final class PasswordResets
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetLinks $links,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @throws Unprocessable lien inconnu, expiré ou déjà utilisé */
    public function reset(ResetLink $link, string $password): void
    {
        $user = Uuid::isValid($link->userId) ? $this->users->find($link->userId) : null;
        if (null === $user || !$this->links->isValid($user, $link->expires, $link->token)) {
            throw new Unprocessable('Lien invalide, expiré ou déjà utilisé : demande un nouveau lien.');
        }

        $user->changePassword($this->hasher->hashPassword($user, $password));
        $user->markVerified();
        // Toute session ouverte avec l'ancien mot de passe prend fin
        $user->revokeSessions();
        $this->em->flush();
    }
}
