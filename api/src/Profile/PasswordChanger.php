<?php

namespace App\Profile;

use App\Dto\PasswordChange;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Changement de mot de passe. Le mot de passe actuel est vérifié ici, une fois l'essai décompté par
 * le contrôleur : vérifié avec le reste de la requête, il pourrait être deviné sans limite.
 */
final class PasswordChanger
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @throws UnprocessableEntityHttpException mot de passe actuel incorrect, signalé sur son champ comme toute validation */
    public function change(User $user, PasswordChange $input): void
    {
        $violations = $this->validator->validate($input, groups: [PasswordChange::CURRENT_PASSWORD]);
        if (\count($violations) > 0) {
            $messages = array_map(static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(), iterator_to_array($violations));

            throw new UnprocessableEntityHttpException(implode("\n", $messages), new ValidationFailedException($input, $violations));
        }

        $user->changePassword($this->hasher->hashPassword($user, $input->newPassword));
        $this->em->flush();
    }
}
