<?php

namespace App\Tests\Double;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * Authentificateur logiciel pour les tests : une vraie clé ES256 (P-256) qui répond comme un
 * téléphone (attestation « none », utilisateur présent et vérifié, compteur croissant).
 */
final class SoftwareAuthenticator
{
    private const PRESENT = 0x01;
    private const VERIFIED = 0x04;
    private const ATTESTED = 0x40;

    private \OpenSSLAsymmetricKey $key;
    private string $credentialId;
    private string $userHandle = '';
    private int $counter = 0;

    public function __construct(
        private string $origin = 'http://localhost:5173',
        private readonly string $rpId = 'localhost',
    ) {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => \OPENSSL_KEYTYPE_EC]);
        if (false === $key) {
            throw new \RuntimeException('Clé de test impossible à créer.');
        }
        $this->key = $key;
        $this->credentialId = random_bytes(16);
    }

    /** Même clé, présentée par un autre site (hameçonnage) */
    public function on(string $origin): self
    {
        $copy = clone $this;
        $copy->origin = $origin;

        return $copy;
    }

    /**
     * Réponse à navigator.credentials.create() (JSON de WebAuthn).
     *
     * @param array<mixed> $options options reçues de l'API
     */
    public function create(array $options): string
    {
        $user = $options['user'] ?? null;
        \assert(\is_array($user) && \is_string($user['id'] ?? null) && \is_string($options['challenge'] ?? null));
        $this->userHandle = Base64UrlSafe::decodeNoPadding($user['id']);

        $authData = hash('sha256', $this->rpId, true).\chr(self::PRESENT | self::VERIFIED | self::ATTESTED).pack('N', 0)
            .str_repeat("\0", 16).pack('n', \strlen($this->credentialId)).$this->credentialId.$this->coseKey();
        $attestation = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return $this->credential([
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($this->clientData('webauthn.create', $options['challenge'])),
            'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestation),
            'transports' => ['internal'],
        ]);
    }

    /**
     * Réponse à navigator.credentials.get() : signature des données de l'authentificateur et du client.
     *
     * @param array<mixed> $options options reçues de l'API
     */
    public function get(array $options): string
    {
        \assert(\is_string($options['challenge'] ?? null));
        $clientData = $this->clientData('webauthn.get', $options['challenge']);
        $authData = hash('sha256', $this->rpId, true).\chr(self::PRESENT | self::VERIFIED).pack('N', ++$this->counter);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, \OPENSSL_ALGO_SHA256);
        \assert(\is_string($signature));

        return $this->credential([
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded($signature),
            'userHandle' => Base64UrlSafe::encodeUnpadded($this->userHandle),
        ]);
    }

    /** @param array<string, mixed> $response */
    private function credential(array $response): string
    {
        $id = Base64UrlSafe::encodeUnpadded($this->credentialId);

        return json_encode(['id' => $id, 'rawId' => $id, 'type' => 'public-key', 'response' => $response, 'clientExtensionResults' => new \stdClass()], \JSON_THROW_ON_ERROR);
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $this->origin, 'crossOrigin' => false], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /** Clé publique au format COSE (EC2, P-256, ES256) */
    private function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->key);
        $ec = \is_array($details) && \is_array($details['ec'] ?? null) ? $details['ec'] : [];
        if (!\is_string($ec['x'] ?? null) || !\is_string($ec['y'] ?? null)) {
            throw new \RuntimeException('Clé de test illisible.');
        }
        $x = str_pad($ec['x'], 32, "\0", \STR_PAD_LEFT);
        $y = str_pad($ec['y'], 32, "\0", \STR_PAD_LEFT);

        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));
    }
}
