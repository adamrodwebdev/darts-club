<?php

namespace App\Security;

use App\Entity\OAuthIdentity;
use App\Entity\User;
use App\Preference\Language;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
    }

    public function resolve(string $provider, string $subject, ?string $email, bool $emailVerified, Language $language = Language::French): User
    {
        $identity = $this->identities->findOneByProviderSubject($provider, $subject);
        if (null !== $identity) {
            return $identity->getUser();
        }

        // Sans email vérifié, on ne peut ni lier ni créer un compte en sécurité
        if (null === $email || '' === $email || !$emailVerified) {
            throw new CustomUserMessageAuthenticationException('Email non vérifié par le fournisseur.');
        }

        try {
            $address = EmailAddress::fromString($email);
        } catch (\InvalidArgumentException) {
            throw new CustomUserMessageAuthenticationException('Email du fournisseur invalide.');
        }

        $user = $this->users->findOneByEmail($address);

        if (null === $user) {
            // Enregistre UserRegistered(viaOAuth), publié après le flush
            $user = User::registerViaOAuth($address, $language);
            $this->em->persist($user);
        } else {
            // Le fournisseur prouve la possession de l'adresse
            $user->verifyThroughProvider();
        }

        $this->em->persist(new OAuthIdentity($user, $provider, $subject));
        $this->em->flush();

        return $user;
    }
}
