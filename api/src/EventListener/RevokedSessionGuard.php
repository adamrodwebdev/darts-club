<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\SessionRevocation;
use App\Translation\ErrorMessages;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Session ouverte avant que l'adhérent ne ferme ses autres sessions (SessionRevocation) : elle
 * prend fin à sa prochaine requête. Après le pare-feu (8), comme SuspendedUserGuard.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1)]
final class RevokedSessionGuard
{
    public function __construct(
        private readonly ErrorMessages $messages,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $user = $event->isMainRequest() ? $this->security->getUser() : null;
        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();
        $generation = $request->hasSession() ? $request->getSession()->get(SessionRevocation::GENERATION) : null;
        if (!$user->isSessionRevoked(\is_int($generation) ? $generation : null)) {
            return;
        }

        $this->security->logout(validateCsrfToken: false);
        $event->setResponse(new JsonResponse(
            ['error' => $this->messages->trans('Ta session a été fermée pour protéger ton compte. Reconnecte-toi.', $request), 'code' => 'session_ended'],
            Response::HTTP_UNAUTHORIZED,
        ));
    }
}
