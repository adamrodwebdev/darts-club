<?php

namespace App\Notification\Push;

/** Adresse Web Push d'un navigateur (PushSubscription.toJSON()) : URL du service et clés de chiffrement */
final readonly class PushEndpoint
{
    /**
     * Services push des navigateurs, seuls destinataires possibles des envois : Chrome, Edge sur Android,
     * Opera, Samsung (Google), Firefox (Mozilla), Edge (Microsoft), Safari (Apple). Toute autre adresse
     * ferait du serveur un relais vers des machines internes ou un hôte qui ne répond jamais.
     */
    private const SERVICES = ['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'];

    public function __construct(
        public string $url,
        public string $publicKey,
        public string $authToken,
    ) {
        if (!str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('Le point de terminaison push doit être en HTTPS.');
        }
        if (!self::isBrowserService($url)) {
            throw new \InvalidArgumentException('Service push inconnu : seuls ceux des navigateurs sont acceptés.');
        }
    }

    /** Service push (hôte) sans l'adresse personnelle du navigateur */
    public function service(): string
    {
        return (string) parse_url($this->url, \PHP_URL_HOST);
    }

    /** Hôte d'un service connu (ou l'un de ses sous-domaines), sans port ni identifiants dans l'URL */
    private static function isBrowserService(string $url): bool
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        foreach (self::SERVICES as $service) {
            if ($host === $service || str_ends_with($host, '.'.$service)) {
                return true;
            }
        }

        return false;
    }
}
