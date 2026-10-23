<?php

namespace App\Security;

/**
 * Source d'aléa cryptographique, injectée : aucun code métier n'appelle
 * random_bytes() directement (substituable dans les tests).
 */
interface TokenGenerator
{
    /** @param positive-int $length */
    public function bytes(int $length): string;

    /** Jeton hexadécimal de $bytes octets aléatoires (2 × $bytes caractères) */
    public function hex(int $bytes): string;

    /** Jeton base64url sans remplissage, sûr dans une URL */
    public function urlSafe(int $bytes): string;
}
