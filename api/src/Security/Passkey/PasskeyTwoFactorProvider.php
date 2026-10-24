<?php

namespace App\Security\Passkey;

use App\Entity\User;
use App\Repository\PasskeyRepository;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deuxième étape de la connexion du bureau : après le mot de passe, la clé d'accès remplace le code
 * à 6 chiffres. Le « code » reçu est la signature de l'appareil (JSON de WebAuthn). L'administrateur
 * garde son code TOTP : ce fournisseur ne le concerne pas.
 */
#[AutoconfigureTag('scheb_two_factor.provider', ['alias' => 'passkey'])]
final class PasskeyTwoFactorProvider implements TwoFactorProviderInterface, TwoFactorFormRendererInterface
{
    public function __construct(
        private readonly PasskeyRepository $passkeyRepository,
        private readonly PasskeyCeremonies $ceremonies,
        private readonly Passkeys $passkeys,
    ) {
    }

    /** Après le mot de passe seulement : une connexion par clé a déjà fait ce geste */
    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof User && $user->requiresPasskey() && $user->hasPasskey()
            && !$context->getPassport()->hasBadge(PasskeyBadge::class);
    }

    public function needsPreparation(): bool
    {
        return false;
    }

    public function prepareAuthentication(object $user): void
    {
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        try {
            $passkey = $this->passkeyRepository->findOneByCredentialId($this->ceremonies->credentialId($authenticationCode));
            if (null === $passkey || !$passkey->getUser()->getId()->equals($user->getId())) {
                return false;
            }
            $this->passkeys->confirmUse($passkey, $authenticationCode, PasskeyCeremonies::twoFactor($user));
        } catch (InvalidPasskey) {
            return false;
        }

        return true;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this;
    }

    /** API JSON : la page de connexion demande les options (/api/auth/2fa/passkey) et lance la clé elle-même */
    public function renderForm(Request $request, array $templateVars): Response
    {
        return new JsonResponse(['twoFactorRequired' => true, 'method' => 'passkey'], Response::HTTP_UNAUTHORIZED);
    }
}
