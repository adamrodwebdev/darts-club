<?php

namespace App\Security\Passkey;

use Webauthn\CredentialRecord;

/**
 * Nom d'une clé d'accès dans la liste de l'adhérent : le gestionnaire de clés quand il se nomme
 * (identifiant AAGUID connu), sinon l'appareil qui l'a créée (navigateur). Vide si inconnu : le site
 * affiche alors « Clé d'accès » dans la langue de l'adhérent.
 */
final class PasskeyLabel
{
    /** Gestionnaires de clés courants (liste publique « passkey-authenticator-aaguids ») */
    private const PROVIDERS = [
        'fbfc3007-154e-4ecc-8c0b-6e020557d7bd' => 'iCloud',
        'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4' => 'Google',
        '08987058-cadc-4b81-b6e1-30de50dcbe96' => 'Windows Hello',
        '9ddd1817-af5a-4672-a2b9-3e3dd95000a9' => 'Windows Hello',
        '6028b017-b1d4-4c02-b4b3-afcdafc96bb2' => 'Windows Hello',
        '53414d53-554e-4700-0000-000000000000' => 'Samsung Pass',
        'adce0002-35bc-c60a-648b-0b25f1f05503' => 'Chrome',
        'bada5566-a7aa-401f-bd96-45619a55120d' => '1Password',
        'd548826e-79b4-db40-a3d8-11116f7e8349' => 'Bitwarden',
    ];

    /** Système de l'appareil, d'après le navigateur (du plus précis au plus général) */
    private const DEVICES = ['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Macintosh' => 'Mac', 'CrOS' => 'Chromebook', 'Linux' => 'Linux'];

    public static function of(CredentialRecord $record, string $userAgent): string
    {
        $provider = self::PROVIDERS[$record->aaguid->toRfc4122()] ?? null;
        if (null !== $provider) {
            return $provider;
        }

        foreach (self::DEVICES as $marker => $device) {
            if (str_contains($userAgent, $marker)) {
                return $device;
            }
        }

        return '';
    }
}
