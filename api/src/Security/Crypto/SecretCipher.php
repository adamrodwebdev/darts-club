<?php

namespace App\Security\Crypto;

use App\Security\TokenGenerator;

/**
 * Chiffrement symétrique authentifié (libsodium secretbox) des secrets
 * stockés en base, comme la graine TOTP. Une fuite de la base seule ne
 * permet pas de générer des codes 2FA.
 */
final class SecretCipher
{
    private const PREFIX = 'v1:';

    /** @var non-empty-string|null */
    private ?string $key = null;

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $base64Key,
        private readonly TokenGenerator $tokens,
    ) {
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = $this->tokens->bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.sodium_bin2base64($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key()), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new \RuntimeException('Format de secret chiffré inconnu.');
        }

        $raw = sodium_base642bin(substr($ciphertext, \strlen(self::PREFIX)), \SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key());

        if (false === $plaintext) {
            throw new \RuntimeException('Secret chiffré invalide ou clé incorrecte.');
        }

        return $plaintext;
    }

    /** @return non-empty-string */
    private function key(): string
    {
        if (null === $this->key) {
            $key = base64_decode($this->base64Key, true);
            if (false === $key || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($key)) {
                throw new \RuntimeException('TWO_FACTOR_ENCRYPTION_KEY doit être une clé de 32 octets encodée en base64.');
            }
            $this->key = $key;
        }

        return $this->key;
    }
}
