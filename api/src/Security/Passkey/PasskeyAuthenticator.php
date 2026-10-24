<?php

namespace App\Security\Passkey;

use App\Repository\PasskeyRepository;
use App\Security\ClientNetwork;
use App\Security\TwoFactor\JsonTwoFactorHandlers;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Connexion par clé d'accès, sans mot de passe : un seul geste (empreinte, visage, code de
 * l'appareil), qui vaut deux facteurs. L'administrateur passe ensuite par son code TOTP (scheb).
 */
final class PasskeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly PasskeyRepository $passkeyRepository,
        private readonly Passkeys $passkeys,
        private readonly PasskeyCeremonies $ceremonies,
        private readonly RateLimiterFactoryInterface $passkeyLoginLimiter,
        private readonly JsonTwoFactorHandlers $handlers,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'auth_passkey_login' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        // Essais limités par origine, avant toute vérification
        if (!$this->passkeyLoginLimiter->create((string) ClientNetwork::of($request))->consume()->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }

        $json = $request->getContent();
        try {
            $passkey = $this->passkeyRepository->findOneByCredentialId($this->ceremonies->credentialId($json));
            if (null === $passkey) {
                throw new InvalidPasskey('Clé inconnue.');
            }
            $this->passkeys->confirmUse($passkey, $json, PasskeyCeremonies::LOGIN);
        } catch (InvalidPasskey) {
            throw new CustomUserMessageAuthenticationException('Clé d\'accès refusée ou inconnue de ce site.');
        }

        $user = $passkey->getUser();

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user), [new PasskeyBadge()]);
    }

    /** Même réponse qu'une connexion par mot de passe : profil, ou code TOTP attendu (administrateur) */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return $this->handlers->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'error' => $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security', $request->getLocale()),
            'code' => $exception instanceof TooManyLoginAttemptsAuthenticationException ? 'too_many_attempts' : 'passkey_refused',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
