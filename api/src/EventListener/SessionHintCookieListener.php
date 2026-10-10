<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Cookie lisible par le front (sans valeur sensible) indiquant qu'une session
 * existe : le front n'interroge /api/auth/me que dans ce cas, ce qui évite
 * des 401 inutiles pour les visiteurs anonymes.
 */
final class SessionHintCookieListener
{
    public const NAME = 'dc_session';
    private const FLAG = '_dc_session_hint';

    /** json_login n'a pas encore de réponse à ce stade : on marque la requête. */
    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $event->getRequest()->attributes->set(self::FLAG, true);
    }

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && true === $event->getRequest()->attributes->get(self::FLAG)) {
            $event->getResponse()->headers->setCookie(
                Cookie::create(self::NAME, '1', 0, '/', null, null, false, false, Cookie::SAMESITE_LAX),
            );
        }
    }

    /** S'exécute après LogoutListener, qui fournit la réponse. */
    #[AsEventListener(priority: -10)]
    public function onLogout(LogoutEvent $event): void
    {
        $event->getResponse()?->headers->clearCookie(self::NAME, '/', null, false, false, Cookie::SAMESITE_LAX);
    }
}
