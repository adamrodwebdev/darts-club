<?php

namespace App\Security\Passkey;

use App\Entity\Passkey;
use App\Entity\User;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Cérémonies WebAuthn du site (bibliothèque web-auth) : options envoyées au navigateur, puis
 * vérification de ce qu'il renvoie. Le site est la seule origine acceptée (une clé ne s'ouvre pas
 * sur un faux site) et l'empreinte, le visage ou le code de l'appareil est exigé à chaque usage.
 */
final class PasskeyCeremonies
{
    public const LOGIN = 'login';
    private const TIMEOUT = 300_000;

    private readonly SerializerInterface $serializer;
    private readonly CeremonyStepManagerFactory $steps;

    public function __construct(
        private readonly PasskeyChallenges $challenges,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $siteUrl,
        private readonly LoggerInterface $logger,
    ) {
        $this->serializer = (new WebauthnSerializerFactory(new AttestationStatementSupportManager([new NoneAttestationStatementSupport()])))->create();
        $this->steps = new CeremonyStepManagerFactory();
        $this->steps->setAllowedOrigins([rtrim($this->siteUrl, '/')]);
    }

    /** Usage d'un défi de connexion en deux étapes (mot de passe puis clé) pour ce compte */
    public static function twoFactor(User $user): string
    {
        return '2fa:'.$user->getId()->toRfc4122();
    }

    /** @return array<string, mixed> options de navigator.credentials.create(), au format JSON de WebAuthn */
    public function creationOptions(User $user): array
    {
        return $this->json($this->creation($user, $this->challenges->issue(self::registration($user))));
    }

    /**
     * Clé créée par l'appareil, vérifiée (défi, origine, vérification de l'utilisateur, algorithme).
     *
     * @throws InvalidPasskey
     */
    public function register(User $user, string $credentialJson): CredentialRecord
    {
        $credential = $this->credential($credentialJson);
        $response = $credential->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new InvalidPasskey('Réponse d\'enregistrement attendue.');
        }
        $this->challenges->consume($response->clientDataJSON->challenge, self::registration($user));

        try {
            return AuthenticatorAttestationResponseValidator::create($this->steps->creationCeremony())
                ->check($response, $this->creation($user, $response->clientDataJSON->challenge), $this->rpId());
        } catch (\Throwable $e) {
            throw $this->refused($e);
        }
    }

    /**
     * Options de navigator.credentials.get() : toute clé du site (connexion directe), ou celles
     * d'un compte (deuxième étape après le mot de passe).
     *
     * @return array<string, mixed>
     */
    public function requestOptions(string $purpose, ?User $user = null): array
    {
        return $this->json($this->request($this->challenges->issue($purpose), $user));
    }

    /** Identifiant (base64url) de la clé qui a signé, pour la retrouver avant de vérifier */
    public function credentialId(string $credentialJson): string
    {
        return Base64UrlSafe::encodeUnpadded($this->credential($credentialJson)->rawId);
    }

    /**
     * Signature de l'appareil vérifiée avec la clé publique enregistrée ; renvoie l'état à garder
     * (compteur, synchronisation).
     *
     * @throws InvalidPasskey
     */
    public function verify(Passkey $passkey, string $credentialJson, string $purpose): CredentialRecord
    {
        $response = $this->credential($credentialJson)->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new InvalidPasskey('Signature attendue.');
        }
        $this->challenges->consume($response->clientDataJSON->challenge, $purpose);

        $user = self::LOGIN === $purpose ? null : $passkey->getUser();
        $record = $passkey->record();
        try {
            return AuthenticatorAssertionResponseValidator::create($this->steps->requestCeremony())
                ->check($record, $response, $this->request($response->clientDataJSON->challenge, $user), $this->rpId(), $record->userHandle);
        } catch (\Throwable $e) {
            throw $this->refused($e);
        }
    }

    private static function registration(User $user): string
    {
        return 'register:'.$user->getId()->toRfc4122();
    }

    private function creation(User $user, string $challenge): PublicKeyCredentialCreationOptions
    {
        return PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create('Skull Darts 71', $this->rpId()),
            user: PublicKeyCredentialUserEntity::create($user->getEmail(), Base64UrlSafe::decodeNoPadding((string) $user->passkeyHandle()), $user->displayName()->value),
            challenge: $challenge,
            pubKeyCredParams: [PublicKeyCredentialParameters::createPk(-7), PublicKeyCredentialParameters::createPk(-257)],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: array_map(static fn (Passkey $passkey): PublicKeyCredentialDescriptor => $passkey->record()->getPublicKeyCredentialDescriptor(), $user->passkeys()),
            timeout: self::TIMEOUT,
        );
    }

    private function request(string $challenge, ?User $user): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId(),
            allowCredentials: null === $user ? [] : array_map(static fn (Passkey $passkey): PublicKeyCredentialDescriptor => $passkey->record()->getPublicKeyCredentialDescriptor(), $user->passkeys()),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TIMEOUT,
        );
    }

    private function credential(string $json): PublicKeyCredential
    {
        try {
            return $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
        } catch (\Throwable $e) {
            throw $this->refused($e);
        }
    }

    /** @return array<string, mixed> */
    private function json(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($this->serializer->serialize($options, 'json'), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }

    /** Domaine de la clé : l'hôte du site (skulldarts71.fr), sans port */
    private function rpId(): string
    {
        return (string) parse_url($this->siteUrl, \PHP_URL_HOST);
    }

    private function refused(\Throwable $e): InvalidPasskey
    {
        $this->logger->notice('Clé d\'accès refusée : {reason}', ['reason' => $e->getMessage()]);

        return $e instanceof InvalidPasskey ? $e : new InvalidPasskey('Clé d\'accès refusée.', 0, $e);
    }
}
