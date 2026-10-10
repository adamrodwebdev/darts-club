<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Anti force brute sur la saisie du code 2FA (6 chiffres = 1 million de
 * combinaisons) : 5 essais / 5 min par session et par IP.
 * Priorité 16 : après le routeur (32), avant le pare-feu (8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class TwoFactorCheckThrottle
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $twoFactorCheckLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || 'auth_2fa_check' !== $request->attributes->get('_route')) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession()->getId() : '';
        $limit = $this->twoFactorCheckLimiter->create($request->getClientIp().'|'.$session)->consume();

        if (!$limit->isAccepted()) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Trop de codes incorrects, réessaie dans quelques minutes.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
            ));
        }
    }
}
