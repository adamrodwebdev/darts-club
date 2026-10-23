<?php

namespace App\Notification\Push;

/** Adresse Web Push d'un navigateur (PushSubscription.toJSON()) : URL du service et clés de chiffrement */
final readonly class PushEndpoint
{
    public function __construct(
        public string $url,
        public string $publicKey,
        public string $authToken,
    ) {
        if (!str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('Le point de terminaison push doit être en HTTPS.');
        }
    }

    /** Service push (hôte) sans l'adresse personnelle du navigateur */
    public function service(): string
    {
        return (string) parse_url($this->url, \PHP_URL_HOST);
    }
}
