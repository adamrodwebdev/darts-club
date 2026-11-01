<?php

namespace App\Notification;

use App\Security\TokenGenerator;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\RemoteHubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie le hub de bout en bout avec les vrais jetons de l'API : un abonné s'inscrit sur un topic
 * privé de contrôle, l'API y publie, l'abonné doit recevoir la publication. Sert à la CI (hub 1.x
 * lancé avec la configuration de production) et à l'exploitant après une mise à jour du hub.
 */
final class RealtimeHubProbe
{
    private const TOPIC = 'https://darts-club.app/health/realtime';
    private const TIMEOUT_SECONDS = 10.0;

    public function __construct(
        private readonly RemoteHubInterface $hub,
        private readonly HttpClientInterface $http,
        private readonly TokenGenerator $tokens,
    ) {
    }

    public function check(): void
    {
        $factory = $this->hub->getFactory() ?? throw new RealtimeCheckFailed('Le hub n’a pas de fabrique de jetons.');
        $marker = $this->tokens->hex(8);

        try {
            $subscription = $this->http->request('GET', $this->hub->getUrl(), [
                'query' => ['match' => self::TOPIC],
                'headers' => [
                    'Accept' => 'text/event-stream',
                    'Authorization' => 'Bearer '.$factory->create([new Grant([Grant::ACTION_SUBSCRIBE], [self::TOPIC])]),
                ],
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => 2 * self::TIMEOUT_SECONDS,
            ]);
            $status = $subscription->getStatusCode();
            if (200 !== $status) {
                $reason = $subscription->getHeaders(false)['www-authenticate'][0] ?? '';
                throw new RealtimeCheckFailed(\sprintf('Abonnement refusé par le hub : HTTP %d %s', $status, $reason));
            }

            $this->publish($marker);
            foreach ($this->http->stream($subscription, self::TIMEOUT_SECONDS) as $chunk) {
                if ($chunk->isTimeout()) {
                    break;
                }
                if (str_contains($chunk->getContent(), $marker)) {
                    $subscription->cancel();

                    return;
                }
            }
            $subscription->cancel();
        } catch (ExceptionInterface $e) {
            throw new RealtimeCheckFailed('Hub injoignable : '.$e->getMessage(), previous: $e);
        }

        throw new RealtimeCheckFailed('Publication acceptée, mais jamais reçue par l’abonné.');
    }

    private function publish(string $marker): void
    {
        try {
            $this->hub->publish(new Update(self::TOPIC, json_encode(['check' => $marker], \JSON_THROW_ON_ERROR), private: true));
        } catch (\Throwable $e) {
            throw new RealtimeCheckFailed('Publication refusée par le hub : '.$e->getMessage(), previous: $e);
        }
    }
}
