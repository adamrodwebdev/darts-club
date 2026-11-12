<?php

declare(strict_types=1);

namespace App\Evening;

/**
 * Étape d'une commande déjà franchie ou impossible (deux appareils, double appui) : l'API répond
 * 409 et l'écran lit l'état réel. Message en français, clé du domaine « errors ».
 */
final class OrderStepRefused extends \RuntimeException
{
    private const MESSAGES = [
        'en_attente' => 'Commande pas encore prête.',
        'prete' => 'Commande déjà prête.',
        'remise' => 'Commande déjà remise.',
        'annulee' => 'Commande annulée.',
    ];

    public static function because(?KitchenStatus $status): self
    {
        return new self(null === $status ? 'Commande sans plat : rien pour la cuisine.' : self::MESSAGES[$status->value]);
    }
}
