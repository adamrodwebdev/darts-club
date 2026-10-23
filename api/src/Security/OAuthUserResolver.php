<?php

namespace App\Security;

use App\Entity\OAuthIdentity;
use App\Entity\User;
use App\Preference\Language;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
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
        private readonly ClockInterface $clock,
    ) {
    }

    public function resolve(OAuthProfile $profile, Language $language = Language::French): User
    {
        $identity = $this->identities->findOneByProviderSubject($profile->provider, $profile->subject);
        if (null !== $identity) {
            return $identity->getUser();
        }

        // Sans email vérifié, on ne peut ni lier ni créer un compte en sécurité
        $email = $profile->email;
        if (null === $email || '' === $email || !$profile->emailVerified) {
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
            $user = User::registerViaOAuth($address, $language, $this->clock->now());
            $this->em->persist($user);
        } else {
            // Le fournisseur prouve la possession de l'adresse ; le titulaire est prévenu (ProviderLinked)
            $user->linkProvider($profile->provider);
        }

        $this->em->persist(new OAuthIdentity(user: $user, provider: $profile->provider, subject: $profile->subject, now: $this->clock->now()));
        $this->em->flush();

        return $user;
    }
}
