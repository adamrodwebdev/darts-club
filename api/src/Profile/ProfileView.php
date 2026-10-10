<?php

namespace App\Profile;

use App\Entity\User;
use App\Membership\MemberBadges;

/** Représentation JSON du profil de l'adhérent connecté. */
final class ProfileView
{
    public function __construct(
        private readonly MemberBadges $badges,
    ) {
    }

    public static function avatarUrl(User $user): ?string
    {
        $avatar = $user->avatar();

        return null !== $avatar ? '/api/users/'.$user->getId()->toRfc4122().'/avatar?v='.substr($avatar, 0, 8) : null;
    }

    /** @return array<string, mixed> */
    public function of(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'displayName' => $user->displayName()->value,
            'phone' => $user->getPhone(),
            'avatarUrl' => self::avatarUrl($user),
            'roles' => $user->getRoles(),
            'badges' => $this->badges->present($user),
            'hasPassword' => $user->hasPassword(),
            'twoFactorEnabled' => $user->hasTwoFactor(),
            'twoFactorSetupRequired' => $user->mustSetUpTwoFactor(),
        ];
    }
}
