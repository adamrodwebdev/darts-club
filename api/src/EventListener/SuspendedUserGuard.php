<?php

namespace App\EventListener;

use App\Entity\User;
use App\Translation\ErrorMessages;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Un compte suspendu perd immédiatement ses sessions ouvertes : la
 * vérification de connexion seule ne suffirait pas. Après le pare-feu (8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1)]
final class SuspendedUserGuard
{
    public function __construct(
        private readonly ErrorMessages $messages,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $user = $event->isMainRequest() ? $this->security->getUser() : null;
        if (!$user instanceof User || !$user->isSuspended()) {
            return;
        }

        $this->security->logout(validateCsrfToken: false);
        $event->setResponse(new JsonResponse(
            ['error' => $this->messages->trans('Compte suspendu. Contacte le bureau du club.', $event->getRequest()), 'code' => 'account_suspended'],
            Response::HTTP_UNAUTHORIZED,
        ));
    }
}
