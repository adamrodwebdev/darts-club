<?php

namespace App\Security\Passkey;

use App\Entity\Passkey;
use App\Entity\User;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Clock\ClockInterface;

/** Cas d'usage des clés d'accès d'un adhérent : en créer, en retirer, en confirmer l'usage. */
final class Passkeys
{
    public function __construct(
        private readonly PasskeyCeremonies $ceremonies,
        private readonly TokenGenerator $tokens,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Avant une première clé : identifiant WebAuthn du compte, tiré au hasard (jamais l'email) */
    public function prepare(User $user): void
    {
        if (null === $user->passkeyHandle()) {
            $user->assignPasskeyHandle(Base64UrlSafe::encodeUnpadded($this->tokens->bytes(32)));
            $this->em->flush();
        }
    }

    /**
     * Clé créée par l'appareil, vérifiée puis rattachée au compte (PasskeyAdded : email d'alerte).
     *
     * @throws InvalidPasskey
     */
    public function register(User $user, string $credentialJson, string $userAgent): void
    {
        $record = $this->ceremonies->register($user, $credentialJson);
        $user->addPasskey(Passkey::register(
            user: $user,
            record: $record,
            label: PasskeyLabel::of($record, $userAgent),
            now: $this->clock->now(),
        ));
        $this->em->flush();
    }

    /** Clé de l'adhérent retirée ; celle d'un autre compte ne bouge pas. */
    public function remove(User $user, Passkey $passkey): void
    {
        if (!$passkey->getUser()->getId()->equals($user->getId())) {
            return;
        }

        $user->removePasskey($passkey);
        $this->em->flush();
    }

    /**
     * Signature vérifiée (connexion, deuxième étape) ; compteur et date d'usage notés.
     *
     * @throws InvalidPasskey
     */
    public function confirmUse(Passkey $passkey, string $credentialJson, string $purpose): void
    {
        $passkey->recordUse($this->ceremonies->verify($passkey, $credentialJson, $purpose), $this->clock->now());
        $this->em->flush();
    }
}
