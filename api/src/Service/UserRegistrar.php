<?php

namespace App\Service;

use App\Dto\Registration;
use App\Entity\User;
use App\Message\SendAccountExistsEmail;
use App\Message\SendVerificationEmail;
use App\Preference\Language;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\NewAccount;
use App\ValueObject\PhoneNumber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class UserRegistrar
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Le résultat n'est volontairement pas exposé à l'appelant HTTP :
     * un email déjà pris ne doit pas être détectable.
     */
    /** La langue de la requête devient celle du compte (emails, notifications). */
    public function register(Registration $registration, Language $language = Language::French): void
    {
        $email = EmailAddress::fromString($registration->email);

        $existing = $this->users->findOneByEmail($email);
        if (null !== $existing) {
            // Hachage dans tous les cas : même durée de réponse qu'une vraie inscription
            $hash = $this->hashers->getPasswordHasher(User::class)->hash($registration->password);
            if ($existing->isVerified()) {
                $this->bus->dispatch(new SendAccountExistsEmail($existing->getId()->toRfc4122()));

                return;
            }

            // Inscription jamais confirmée : le dernier mot de passe choisi remplace le précédent. Seul le
            // titulaire de l'adresse reçoit le lien, et la confirmation demande ce mot de passe : une
            // inscription faite par quelqu'un d'autre ne bloque plus l'adresse.
            $existing->changePassword($hash);
            $this->em->flush();
            $this->bus->dispatch(new SendVerificationEmail($existing->getId()->toRfc4122()));

            return;
        }

        $user = User::register(
            new NewAccount(
                email: $email,
                phone: null !== $registration->phone && '' !== trim($registration->phone) ? PhoneNumber::fromInput($registration->phone) : null,
                language: $language,
            ),
            $this->hashers->getPasswordHasher(User::class)->hash($registration->password),
            $this->clock->now(),
        );

        // UserRegistered est publié après le flush (DomainEventPublisher)
        $this->em->persist($user);
        $this->em->flush();
    }
}
