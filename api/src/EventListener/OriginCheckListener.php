<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protection CSRF d'une API à session : toute requête navigateur qui
 * modifie l'état doit venir d'une origine de confiance.
 * Priorité 64 : s'exécute avant le pare-feu (8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final class OriginCheckListener
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** @var list<string> */
    private readonly array $trustedOrigins;

    /** @param array<mixed> $trustedOrigins */
    public function __construct(
        #[Autowire('%env(csv:TRUSTED_ORIGINS)%')]
        array $trustedOrigins,
    ) {
        $origins = [];
        foreach ($trustedOrigins as $origin) {
            if (\is_string($origin) && '' !== trim($origin)) {
                $origins[] = rtrim(trim($origin), '/');
            }
        }
        $this->trustedOrigins = $origins;
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || \in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        $origin = $request->headers->get('Origin');

        // Sans Origin : client non-navigateur, ou navigateur en same-origin
        $allowed = null !== $origin
            ? \in_array(rtrim($origin, '/'), [...$this->trustedOrigins, $request->getSchemeAndHttpHost()], true)
            : \in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin', 'none'], true);

        if (!$allowed) {
            $event->setResponse(new JsonResponse(['error' => 'Origine non autorisée.'], Response::HTTP_FORBIDDEN));
        }
    }
}
