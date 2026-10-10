<?php

namespace App\Service;

use App\Dto\Registration;
use App\Entity\User;
use App\Event\UserRegistered;
use App\Message\SendAccountExistsEmail;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserRegistrar
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Le résultat n'est volontairement pas exposé à l'appelant HTTP :
     * un email déjà pris ne doit pas être détectable.
     */
    public function register(Registration $registration): void
    {
        $existing = $this->users->findOneBy(['email' => mb_strtolower(trim($registration->email))]);
        if (null !== $existing) {
            $this->bus->dispatch(new SendAccountExistsEmail($existing->getId()->toRfc4122()));

            return;
        }

        $user = new User($registration->email);
        $user->setPassword($this->hasher->hashPassword($user, $registration->password));
        $user->setPhone(self::toE164($registration->phone));

        $this->em->persist($user);
        $this->em->flush();

        $this->dispatcher->dispatch(new UserRegistered($user->getId()));
    }

    private static function toE164(?string $phone): ?string
    {
        if (null === $phone || '' === trim($phone)) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        return $util->format($util->parse($phone, 'FR'), PhoneNumberFormat::E164);
    }
}
