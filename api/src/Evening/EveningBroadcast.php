<?php

namespace App\Evening;

use App\Entity\ClubEvent;
use App\Notification\RealtimeTopics;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Diffuse aux écrans d'une soirée (accueil, bar, cuisine, bureau) ce qui vient de changer. Le temps
 * réel n'est qu'un confort : un hub en panne n'empêche jamais une vente, les écrans se resynchronisent.
 */
final class EveningBroadcast
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $payload avec sa clé « kind » (sale…) */
    public function send(ClubEvent $event, array $payload): void
    {
        try {
            $this->hub->publish(new Update(RealtimeTopics::evening($event), json_encode($payload, \JSON_THROW_ON_ERROR), private: true));
        } catch (\Throwable $e) {
            $this->logger->error('Diffusion temps réel de la soirée impossible : {error}', ['error' => $e->getMessage(), 'exception' => $e]);
        }
    }
}
