<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Liens « mot de passe oublié » : valables une heure, signés (HMAC) et liés au mot de passe actuel.
 * Dès que le mot de passe change, les liens envoyés avant ne servent plus : un lien sert une fois,
 * sans rien stocker.
 */
final class PasswordResetLinks
{
    private const LIFETIME = 3600;

    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private readonly string $secret,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function create(User $user): string
    {
        $expires = $this->clock->now()->getTimestamp() + self::LIFETIME;

        return rtrim($this->frontendUrl, '/').'/nouveau-mot-de-passe?'.http_build_query([
            'id' => $user->getId()->toRfc4122(),
            'expires' => $expires,
            'token' => $this->token($user, $expires),
        ]);
    }

    public function isValid(User $user, int $expires, string $token): bool
    {
        return $expires >= $this->clock->now()->getTimestamp() && hash_equals($this->token($user, $expires), $token);
    }

    private function token(User $user, int $expires): string
    {
        $mac = hash_hmac('sha256', $user->getId()->toRfc4122().'|'.$expires.'|'.$user->getPassword(), $this->secret, true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}
