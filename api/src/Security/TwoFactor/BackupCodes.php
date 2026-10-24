<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Security\Crypto\SecretCipher;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\ConstantTime\Base32;
use Scheb\TwoFactorBundle\Security\TwoFactor\Backup\BackupCodeManagerInterface;

/**
 * Codes de secours de la double authentification : 80 bits chacun (« k3m9-x2p7-qa4r-8tzd »),
 * stockés en empreinte HMAC à clé du serveur. Une base volée seule ne permet pas de les retrouver.
 * Les codes d'avant octobre 2026 (40 bits, SHA-256 seul) restent valables jusqu'à leur usage.
 */
final class BackupCodes implements BackupCodeManagerInterface
{
    public const COUNT = 10;

    public function __construct(
        private readonly SecretCipher $cipher,
        private readonly TokenGenerator $tokens,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<string> codes en clair, montrés une seule fois à l'adhérent */
    public function generate(): array
    {
        $codes = [];
        for ($i = 0; $i < self::COUNT; ++$i) {
            $codes[] = implode('-', str_split(strtolower(Base32::encodeUnpadded($this->tokens->bytes(10))), 4));
        }

        return $codes;
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string> empreintes à stocker
     */
    public function hashes(array $codes): array
    {
        return array_map(fn (string $code): string => $this->hash($code), $codes);
    }

    public function isBackupCode(object $user, string $code): bool
    {
        return null !== $this->storedHash($user, $code);
    }

    public function invalidateBackupCode(object $user, string $code): void
    {
        $hash = $this->storedHash($user, $code);
        if ($user instanceof User && null !== $hash) {
            $user->useBackupCode($hash);
            $this->em->flush();
        }
    }

    /** Empreinte enregistrée pour ce code (actuelle, sinon ancienne), null si le code n'est pas valable */
    private function storedHash(object $user, string $code): ?string
    {
        if (!$user instanceof User || '' === self::normalize($code)) {
            return null;
        }

        foreach ([$this->hash($code), hash('sha256', self::normalize($code))] as $hash) {
            if ($user->hasBackupCode($hash)) {
                return $hash;
            }
        }

        return null;
    }

    private function hash(string $code): string
    {
        return $this->cipher->keyedHash(self::normalize($code), 'backup-codes');
    }

    /** Saisie tolérante : majuscules, espaces et tirets ignorés */
    private static function normalize(string $code): string
    {
        return strtolower(str_replace([' ', '-'], '', $code));
    }
}
