<?php

namespace App\Service;

use App\Dto\Registration;
use App\Entity\User;
use App\Message\SendAccountExistsEmail;
use App\Preference\Language;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\PhoneNumber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class UserRegistrar
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly MessageBusInterface $bus,
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
            $this->bus->dispatch(new SendAccountExistsEmail($existing->getId()->toRfc4122()));

            return;
        }

        $user = User::register(
            $email,
            $this->hashers->getPasswordHasher(User::class)->hash($registration->password),
            null !== $registration->phone && '' !== trim($registration->phone) ? PhoneNumber::fromInput($registration->phone) : null,
            $language,
        );

        // UserRegistered est publié après le flush (DomainEventPublisher)
        $this->em->persist($user);
        $this->em->flush();
    }
}
