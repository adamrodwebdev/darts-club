<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Security\SessionRevocation;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/** Activation / désactivation de la double authentification TOTP. */
final class TwoFactorEnrollment
{
    public function __construct(
        private readonly TotpAuthenticatorInterface $totp,
        private readonly EntityManagerInterface $em,
        private readonly BackupCodes $backupCodes,
        private readonly SessionRevocation $sessions,
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

    /**
     * Les autres sessions ouvertes sont fermées : si le mot de passe avait fui, la session d'un
     * intrus ne survit pas à l'activation.
     *
     * @return list<string> codes de secours, affichés une seule fois
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactor() || null === $user->getTotpAuthenticationConfiguration()) {
            throw new TwoFactorException('Aucune activation en cours.');
        }
        if (!$this->totp->checkCode($user, self::normalize($code))) {
            throw new TwoFactorException('Code incorrect. Vérifie l’heure de ton téléphone.');
        }

        $codes = $this->backupCodes->generate();
        $user->enableTotp($this->backupCodes->hashes($codes));
        $this->em->flush();
        $this->sessions->endOtherSessions($user);

        return $codes;
    }

    public function disable(User $user, string $code): void
    {
        if (!$this->totp->checkCode($user, self::normalize($code)) && !$this->backupCodes->isBackupCode($user, $code)) {
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
