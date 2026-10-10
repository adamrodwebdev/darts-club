<?php

namespace App\Security;

use App\Entity\OAuthIdentity;
use App\Entity\User;
use App\Event\UserRegistered;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Associe une identité OAuth à un compte : identité connue, sinon compte
 * existant avec le même email (vérifié par le fournisseur), sinon nouveau compte.
 */
final class OAuthUserResolver
{
    public function __construct(
        private readonly OAuthIdentityRepository $identities,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function resolve(string $provider, string $subject, ?string $email, bool $emailVerified): User
    {
        $identity = $this->identities->findOneByProviderSubject($provider, $subject);
        if (null !== $identity) {
            return $identity->getUser();
        }

        // Sans email vérifié, on ne peut ni lier ni créer un compte en sécurité
        if (null === $email || '' === $email || !$emailVerified) {
            throw new CustomUserMessageAuthenticationException('Email non vérifié par le fournisseur.');
        }

        $user = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);
        $isNew = null === $user;

        if ($isNew) {
            $user = new User($email);
            $this->em->persist($user);
        }
        $user->markVerified();

        $this->em->persist(new OAuthIdentity($user, $provider, $subject));
        $this->em->flush();

        if ($isNew) {
            $this->dispatcher->dispatch(new UserRegistered($user->getId(), viaOAuth: true));
        }

        return $user;
    }
}
