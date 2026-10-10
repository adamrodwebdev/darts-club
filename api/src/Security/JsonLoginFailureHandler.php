<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Échec de connexion : message traduit (domaine « security ») et code stable,
 * pour que le front réagisse sans dépendre du texte (ex. renvoi du lien de confirmation).
 */
final class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $code = match (true) {
            $exception instanceof AccountNotVerified => 'email_not_verified',
            $exception instanceof AccountSuspended => 'account_suspended',
            $exception instanceof TooManyLoginAttemptsAuthenticationException => 'too_many_attempts',
            default => 'invalid_credentials',
        };

        return new JsonResponse([
            'error' => $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security', $request->getLocale()),
            'code' => $code,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
