<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Origine d'une requête pour les limites de débit : l'adresse IPv4, ou le réseau /64 d'une adresse
 * IPv6. Un abonnement IPv6 reçoit tout un /64 : avec une clé par adresse, une seule machine en
 * changerait à volonté et échapperait à toute limite.
 */
final readonly class ClientNetwork
{
    private const IPV4_MAPPED = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    private function __construct(private string $key)
    {
    }

    public static function of(Request $request): self
    {
        $ip = (string) $request->getClientIp();
        $packed = @inet_pton($ip);
        if (false === $packed || 16 !== \strlen($packed) || str_starts_with($packed, self::IPV4_MAPPED)) {
            return new self($ip);
        }

        $network = inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8));

        return new self(false !== $network ? $network.'/64' : $ip);
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
