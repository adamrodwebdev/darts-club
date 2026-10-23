<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpFactory;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Codes TOTP à usage unique : un code accepté (connexion, activation, désactivation) ne sert plus,
 * pas plus qu'un code plus ancien. Un code intercepté (épaule, faux site relais) ne rouvre donc pas
 * une deuxième session pendant sa minute de validité. Même tolérance d'horloge que scheb (leeway).
 */
#[AsDecorator('scheb_two_factor.security.totp_authenticator')]
final class SingleUseTotpAuthenticator implements TotpAuthenticatorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly TotpAuthenticatorInterface $inner,
        #[Autowire(service: 'scheb_two_factor.security.totp_factory')]
        private readonly TotpFactory $totps,
        #[Autowire('%scheb_two_factor.totp.leeway%')]
        private readonly int $leeway,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function checkCode(TwoFactorInterface $user, string $code): bool
    {
        $code = str_replace(' ', '', $code);
        if (!$user instanceof User || '' === $code || null === $user->getTotpAuthenticationConfiguration()) {
            return false;
        }

        $totp = $this->totps->createTotpForUser($user);
        $now = $this->clock->now()->getTimestamp();
        foreach ([$now - $this->leeway, $now, $now + $this->leeway] as $timestamp) {
            if ($timestamp < 0 || !hash_equals($totp->at($timestamp), $code)) {
                continue;
            }

            $timestep = intdiv($timestamp - $totp->getEpoch(), $totp->getPeriod());
            if (!$user->acceptsTotpTimestep($timestep)) {
                return false;
            }
            $user->useTotpTimestep($timestep);
            $this->em->flush();

            return true;
        }

        return false;
    }

    public function getQRContent(TwoFactorInterface $user): string
    {
        return $this->inner->getQRContent($user);
    }

    public function generateSecret(): string
    {
        return $this->inner->generateSecret();
    }
}
