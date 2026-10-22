<?php

namespace App\Security;

/** Aléa du système (CSPRNG) */
final class SecureTokenGenerator implements TokenGenerator
{
    public function bytes(int $length): string
    {
        return random_bytes(max(1, $length));
    }

    public function hex(int $bytes): string
    {
        return bin2hex(random_bytes(max(1, $bytes)));
    }

    public function urlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes(max(1, $bytes))), '+/', '-_'), '=');
    }
}
