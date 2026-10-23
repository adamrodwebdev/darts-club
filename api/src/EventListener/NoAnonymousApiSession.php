<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Un visiteur anonyme qui appelle une route protégée reçoit un 401, et Symfony retient l'adresse
 * demandée dans une session toute neuve (pour rediriger après la connexion, inutile à une API).
 * Retirée ici, cette session reste vide : ni cookie, ni écriture dans Redis. Sinon, chaque requête
 * anonyme créerait une session, et un flot de requêtes remplirait Redis aux dépens des vraies.
 */
#[AsEventListener]
final class NoAnonymousApiSession
{
    use TargetPathTrait;

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasSession() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $session = $request->getSession();
        if ($session->isStarted()) {
            $this->removeTargetPath($session, 'main');
        }
    }
}
