<?php

namespace App\Dto;

use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordChange
{
    /** Mot de passe actuel : vérifié par le contrôleur après la limite d'essais (sinon devinable sans fin) */
    public const CURRENT_PASSWORD = 'current_password';

    public function __construct(
        #[UserPassword(message: 'Mot de passe actuel incorrect.', groups: [self::CURRENT_PASSWORD])]
        #[\SensitiveParameter]
        public readonly string $currentPassword = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 12, max: 4096)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        #[Assert\NotCompromisedPassword]
        #[Assert\NotEqualTo(propertyPath: 'currentPassword', message: 'Le nouveau mot de passe doit être différent.')]
        #[\SensitiveParameter]
        public readonly string $newPassword = '',
    ) {
    }
}
