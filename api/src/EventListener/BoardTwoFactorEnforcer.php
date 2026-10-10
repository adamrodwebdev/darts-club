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
 * Tant qu'un membre du bureau n'a pas activé la 2FA, seules les routes
 * permettant de la configurer (et de gérer son compte) restent accessibles.
 * Priorité 0 : après le pare-feu (8), l'utilisateur est connu.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class BoardTwoFactorEnforcer
{
    private const ALLOWED = ['/api/auth/', '/api/me/', '/api/realtime/', '/api/notifications', '/api/push/'];

    public function __construct(
        private readonly ErrorMessages $messages,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!$event->isMainRequest() || !str_starts_with($path, '/api/')) {
            return;
        }
        if ('/api/me' === $path) {
            return;
        }
        foreach (self::ALLOWED as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $this->security->isGranted('IS_AUTHENTICATED_FULLY') && $user->mustSetUpTwoFactor()) {
            $event->setResponse(new JsonResponse(
                ['error' => $this->messages->trans('Active la double authentification pour accéder aux fonctions du bureau.', $event->getRequest()), 'code' => 'two_factor_setup_required'],
                Response::HTTP_FORBIDDEN,
            ));
        }
    }
}
