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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Réponses JSON de la connexion pour une SPA :
 * - mot de passe correct mais 2FA attendue → 202 {twoFactorRequired: true}
 * - connexion complète → 200 + profil
 * - code 2FA erroné → 401
 */
final class JsonTwoFactorHandlers implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, AuthenticationRequiredHandlerInterface
{
    public function __construct(
        private readonly ErrorMessages $messages,
        private readonly ProfileView $view,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        if ($token instanceof TwoFactorTokenInterface) {
            return new JsonResponse(['twoFactorRequired' => true], Response::HTTP_ACCEPTED);
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(status: Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse($this->view->of($user));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => $this->messages->trans('Code de vérification incorrect.', $request)], Response::HTTP_UNAUTHORIZED);
    }

    public function onAuthenticationRequired(Request $request, TokenInterface $token): Response
    {
        return new JsonResponse(
            ['error' => $this->messages->trans('Code de double authentification requis.', $request), 'twoFactorRequired' => true],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
