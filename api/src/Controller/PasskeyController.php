<?php

namespace App\Controller;

use App\Entity\Passkey;
use App\Entity\User;
use App\Security\ClientNetwork;
use App\Security\Passkey\InvalidPasskey;
use App\Security\Passkey\PasskeyCeremonies;
use App\Security\Passkey\Passkeys;
use App\Security\RecentLogin;
use App\Security\RequestThrottle;
use App\Translation\ErrorMessages;
use App\Translation\Unprocessable;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Clés d'accès (passkeys) : connexion sans mot de passe, deuxième étape du bureau, gestion par l'adhérent. */
final class PasskeyController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly PasskeyCeremonies $ceremonies,
        private readonly Passkeys $passkeys,
        private readonly RequestThrottle $requestThrottle,
        private readonly RecentLogin $recentLogin,
        private readonly ErrorMessages $messages,
    ) {
    }

    /** Options de connexion directe : n'importe quelle clé du site (le navigateur propose les siennes) */
    #[Route('/api/auth/passkey/options', name: 'auth_passkey_options', methods: ['POST'], format: 'json')]
    public function loginOptions(Request $request, RateLimiterFactoryInterface $passkeyLoginLimiter): JsonResponse
    {
        $this->requestThrottle->consume($passkeyLoginLimiter, (string) ClientNetwork::of($request));

        return $this->json($this->ceremonies->requestOptions(PasskeyCeremonies::LOGIN));
    }

    /** Intercepté par le pare-feu (PasskeyAuthenticator). */
    #[Route('/api/auth/passkey/login', name: 'auth_passkey_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }

    /** Deuxième étape (mot de passe accepté) : options pour les clés de ce compte */
    #[Route('/api/auth/2fa/passkey', name: 'auth_2fa_passkey', methods: ['POST'], format: 'json')]
    public function twoFactorOptions(Security $security): JsonResponse
    {
        $token = $security->getToken();
        $user = $token instanceof TwoFactorTokenInterface ? $token->getUser() : null;
        if (!$user instanceof User || !$user->hasPasskey()) {
            throw new AccessDeniedHttpException();
        }

        return $this->json($this->ceremonies->requestOptions(PasskeyCeremonies::twoFactor($user), $user));
    }

    #[Route('/api/me/passkeys', name: 'passkeys_list', methods: ['GET'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['items' => array_map(static fn (Passkey $passkey): array => $passkey->toArray(), $user->passkeys())]);
    }

    /** Options de création ; dans les 10 minutes qui suivent une connexion */
    #[Route('/api/me/passkeys/options', name: 'passkeys_options', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function creationOptions(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        if (!$this->recentLogin->isRecent($request)) {
            return $this->reauthenticate($request);
        }
        $this->passkeys->prepare($user);

        return $this->json($this->ceremonies->creationOptions($user));
    }

    /** Corps : la clé créée par l'appareil (JSON de WebAuthn) */
    #[Route('/api/me/passkeys', name: 'passkeys_register', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function register(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        if (!$this->recentLogin->isRecent($request)) {
            return $this->reauthenticate($request);
        }

        try {
            $this->passkeys->register($user, $request->getContent(), $request->headers->get('User-Agent', ''));
        } catch (InvalidPasskey $e) {
            throw new Unprocessable('Clé d’accès refusée : réessaie depuis cet appareil.', $e);
        }

        return $this->list($user)->setStatusCode(Response::HTTP_CREATED);
    }

    #[Route('/api/me/passkeys/{id}', name: 'passkeys_remove', requirements: ['id' => self::UUID], methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function remove(#[CurrentUser] User $user, Passkey $passkey, Request $request): Response
    {
        if (!$this->recentLogin->isRecent($request)) {
            return $this->reauthenticate($request);
        }
        $this->passkeys->remove($user, $passkey);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function reauthenticate(Request $request): JsonResponse
    {
        return new JsonResponse(
            ['error' => $this->messages->trans('Reconnecte-toi pour modifier tes clés d’accès (sécurité).', $request), 'code' => 'reauthentication_required'],
            Response::HTTP_FORBIDDEN,
        );
    }
}
