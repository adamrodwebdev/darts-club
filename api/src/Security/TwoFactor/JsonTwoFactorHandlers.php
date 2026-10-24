<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Profile\ProfileView;
use App\Translation\ErrorMessages;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Réponses JSON de la connexion pour une SPA :
 * - mot de passe correct mais 2FA attendue → 202 {twoFactorRequired: true}
 * - connexion complète → 200 + profil
 * - code 2FA erroné → 401 ; au-delà de 10 erreurs par heure sur le compte → 429 et
 *   session fermée (une reconnexion ne redonne pas d'essais)
 */
final class JsonTwoFactorHandlers implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, AuthenticationRequiredHandlerInterface
{
    public function __construct(
        private readonly ErrorMessages $messages,
        private readonly ProfileView $view,
        private readonly RateLimiterFactoryInterface $twoFactorAccountLimiter,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        if ($token instanceof TwoFactorTokenInterface) {
            // Essais épuisés pour ce compte : pas de nouvelle chance en se reconnectant
            if (0 === $this->attempts($token)->consume(0)->getRemainingTokens()) {
                return $this->tooManyAttempts($request);
            }

            // « passkey » (bureau) ou « totp » : la page de connexion sait quoi demander
            return new JsonResponse(['twoFactorRequired' => true, 'method' => $token->getCurrentTwoFactorProvider()], Response::HTTP_ACCEPTED);
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(status: Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse($this->view->of($user));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $token = $this->tokens->getToken();
        if (null !== $token && !$this->attempts($token)->consume()->isAccepted()) {
            return $this->tooManyAttempts($request);
        }

        return new JsonResponse(['error' => $this->messages->trans('Code de vérification incorrect.', $request)], Response::HTTP_UNAUTHORIZED);
    }

    private function attempts(TokenInterface $token): \Symfony\Component\RateLimiter\LimiterInterface
    {
        return $this->twoFactorAccountLimiter->create($token->getUserIdentifier());
    }

    /** Session fermée : il faudra repasser par le mot de passe une fois le délai écoulé. */
    private function tooManyAttempts(Request $request): JsonResponse
    {
        $this->tokens->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return new JsonResponse(
            ['error' => $this->messages->trans('Trop de codes incorrects, réessaie dans quelques minutes.', $request), 'code' => 'too_many_attempts'],
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    public function onAuthenticationRequired(Request $request, TokenInterface $token): Response
    {
        return new JsonResponse(
            ['error' => $this->messages->trans('Code de double authentification requis.', $request), 'twoFactorRequired' => true],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
