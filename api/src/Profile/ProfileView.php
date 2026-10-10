<?php

namespace App\Profile;

use App\Entity\User;

/** Représentation JSON du profil de l'adhérent connecté. */
final class ProfileView
{
    /** @return array<string, mixed> */
    public static function of(User $user): array
    {
        $id = $user->getId()->toRfc4122();

        return [
            'id' => $id,
            'email' => $user->getEmail(),
            'displayName' => $user->displayName()->value,
            'phone' => $user->getPhone(),
            'avatarUrl' => null !== $user->avatar() ? '/api/users/'.$id.'/avatar?v='.substr((string) $user->avatar(), 0, 8) : null,
            'roles' => $user->getRoles(),
            'hasPassword' => $user->hasPassword(),
            'twoFactorEnabled' => $user->hasTwoFactor(),
            'twoFactorSetupRequired' => $user->mustSetUpTwoFactor(),
        ];
    }
}
