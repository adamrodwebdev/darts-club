<?php

namespace App\Security\Passkey;

use App\Security\TokenGenerator;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Défis WebAuthn sans session : 16 octets tirés au hasard, une échéance (5 minutes) et une
 * signature HMAC liée à l'usage (« login », « 2fa:<compte> », « register:<compte> »). Un défi sert
 * une fois (noté dans le cache jusqu'à son échéance). Sans session, la page de connexion n'en
 * ouvre pas une pour chaque visiteur.
 */
final class PasskeyChallenges
{
    private const LIFETIME = 300;
    private const NONCE = 16;
    private const MAC = 16;

    public function __construct(
        private readonly TokenGenerator $tokens,
        private readonly ClockInterface $clock,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $used,
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    /** Défi brut (octets) pour cet usage */
    public function issue(string $purpose): string
    {
        $nonce = $this->tokens->bytes(self::NONCE);
        $expires = pack('J', $this->clock->now()->getTimestamp() + self::LIFETIME);

        return $nonce.$expires.$this->mac($nonce.$expires, $purpose);
    }

    /** @throws InvalidPasskey défi inconnu, d'un autre usage, expiré ou déjà servi */
    public function consume(string $challenge, string $purpose): void
    {
        if (self::NONCE + 8 + self::MAC !== \strlen($challenge)) {
            throw new InvalidPasskey('Défi inconnu.');
        }
        $nonce = substr($challenge, 0, self::NONCE);
        $expires = substr($challenge, self::NONCE, 8);
        if (!hash_equals($this->mac($nonce.$expires, $purpose), substr($challenge, self::NONCE + 8))) {
            throw new InvalidPasskey('Défi inconnu.');
        }

        $expiresAt = unpack('J', $expires);
        if (!\is_array($expiresAt) || !\is_int($expiresAt[1] ?? null) || $expiresAt[1] < $this->clock->now()->getTimestamp()) {
            throw new InvalidPasskey('Défi expiré.');
        }

        $item = $this->used->getItem('passkey_challenge_'.bin2hex($nonce));
        if ($item->isHit()) {
            throw new InvalidPasskey('Défi déjà utilisé.');
        }
        $this->used->save($item->set(true)->expiresAfter(self::LIFETIME));
    }

    private function mac(string $data, string $purpose): string
    {
        return substr(hash_hmac('sha256', $data.'|'.$purpose, $this->secret, true), 0, self::MAC);
    }
}
