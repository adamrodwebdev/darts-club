<?php

namespace App\Security;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Connexion récente : ajouter ou retirer une clé d'accès n'est possible que dans les 10 minutes
 * qui suivent une connexion. Une session volée plus tard ne peut pas y glisser la clé d'un intrus.
 */
final class RecentLogin
{
    private const AT = '_authenticated_at';
    private const WINDOW = 600;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->set(self::AT, $this->clock->now()->getTimestamp());
        }
    }

    public function isRecent(Request $request): bool
    {
        $at = $request->hasSession() ? $request->getSession()->get(self::AT) : null;

        return \is_int($at) && $this->clock->now()->getTimestamp() - $at <= self::WINDOW;
    }
}
