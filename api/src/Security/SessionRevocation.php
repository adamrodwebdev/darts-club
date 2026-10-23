<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Chaque session note la génération de sessions de l'adhérent à sa connexion ; fermer ses autres
 * sessions (2FA activée, accès réinitialisé) fait passer à la génération suivante, et
 * RevokedSessionGuard refuse les sessions restées en arrière. Celle qui fait la demande suit.
 */
final class SessionRevocation
{
    public const GENERATION = '_session_generation';

    public function __construct(
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        if ($user instanceof User && $request->hasSession()) {
            $request->getSession()->set(self::GENERATION, $user->sessionGeneration());
        }
    }

    /** Les autres sessions de l'adhérent sont fermées à leur prochaine requête ; celle-ci reste ouverte. */
    public function endOtherSessions(User $user): void
    {
        $user->revokeSessions();
        $this->em->flush();

        $request = $this->requests->getMainRequest();
        if (null !== $request && $request->hasSession()) {
            $request->getSession()->set(self::GENERATION, $user->sessionGeneration());
        }
    }
}
