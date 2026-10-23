<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Connexion refusée sans que le mot de passe ait été vérifié (adresse inconnue, compte sans mot de
 * passe) : un hachage inutile mais volontaire rend la réponse aussi lente qu'un mauvais mot de passe.
 * Sinon, chronométrer la réponse dirait quelles adresses ont un compte.
 */
#[AsEventListener]
final class LoginTimingEqualizer
{
    public function __construct(
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        if ('auth_login' !== $event->getRequest()->attributes->get('_route') || !$this->passwordWasNotChecked($event)) {
            return;
        }

        $this->hashers->getPasswordHasher(User::class)->hash('mot de passe jamais vérifié');
    }

    private function passwordWasNotChecked(LoginFailureEvent $event): bool
    {
        $exception = $event->getException();
        // Limite d'essais atteinte : réponse immédiate pour tout le monde, sans calcul de plus
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return false;
        }
        for ($e = $exception; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof UserNotFoundException) {
                return true;
            }
        }

        try {
            $user = $event->getPassport()?->getUser();
        } catch (AuthenticationException) {
            return true;
        }

        return $user instanceof User && !$user->hasPassword();
    }
}
