<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/** Activation / désactivation de la double authentification TOTP. */
final class TwoFactorEnrollment
{
    private const BACKUP_CODE_COUNT = 10;

    public function __construct(
        private readonly TotpAuthenticatorInterface $totp,
        private readonly EntityManagerInterface $em,
        private readonly TokenGenerator $tokens,
    ) {
    }

    /** @return array{secret: string, uri: string, qrCode: string} */
    public function start(User $user): array
    {
        if ($user->hasTwoFactor()) {
            throw new TwoFactorException('La double authentification est déjà active.');
        }

        $secret = $this->totp->generateSecret();
        $user->startTotpEnrollment($secret);
        $this->em->flush();

        $uri = $this->totp->getQRContent($user);
        $qrCode = (new Builder(writer: new SvgWriter(), data: $uri, size: 240, margin: 8))->build()->getDataUri();

        return ['secret' => $secret, 'uri' => $uri, 'qrCode' => $qrCode];
    }

    /** @return list<string> codes de secours, affichés une seule fois */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactor() || null === $user->getTotpAuthenticationConfiguration()) {
            throw new TwoFactorException('Aucune activation en cours.');
        }
        if (!$this->totp->checkCode($user, self::normalize($code))) {
            throw new TwoFactorException('Code incorrect. Vérifie l’heure de ton téléphone.');
        }

        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODE_COUNT; ++$i) {
            $raw = strtolower($this->tokens->hex(5));
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        $user->enableTotp($codes);
        $this->em->flush();

        return $codes;
    }

    public function disable(User $user, string $code): void
    {
        $normalized = self::normalize($code);
        if (!$this->totp->checkCode($user, $normalized) && !$user->isBackupCode($code)) {
            throw new TwoFactorException('Code incorrect.');
        }

        $user->disableTotp();
        $this->em->flush();
    }

    private static function normalize(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }
}
