<?php

declare(strict_types=1);

namespace App\Dto;

use App\Text\Secret;
use App\Validator\PasswordPolicy;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordChange
{
    /** Mot de passe actuel : vérifié par le contrôleur après la limite d'essais (sinon devinable sans fin) */
    public const CURRENT_PASSWORD = 'current_password';

    public function __construct(
        #[UserPassword(message: 'Mot de passe actuel incorrect.', groups: [self::CURRENT_PASSWORD])]
        #[\SensitiveParameter]
        #[Assert\Length(max: 4096)]
        #[Secret]
        public readonly string $currentPassword = '',

        #[PasswordPolicy]
        #[Assert\NotEqualTo(propertyPath: 'currentPassword', message: 'Le nouveau mot de passe doit être différent.')]
        #[\SensitiveParameter]
        #[Secret]
        public readonly string $newPassword = '',
    ) {
    }
}
