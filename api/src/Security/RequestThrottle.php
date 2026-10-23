<?php

namespace App\Security;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limitation de débit des requêtes : au-delà de la limite, réponse 429 avec le
 * délai d'attente (Retry-After), calculé avec l'horloge injectée.
 */
final class RequestThrottle
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /** @throws TooManyRequestsHttpException */
    public function consume(RateLimiterFactoryInterface $limiter, string $key, int $tokens = 1): void
    {
        $limit = $limiter->create($key)->consume($tokens);
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($this->secondsUntil($limit->getRetryAfter()));
        }
    }

    /** Secondes avant de pouvoir réessayer (au moins 1) */
    public function secondsUntil(\DateTimeImmutable $retryAfter): int
    {
        return max(1, $retryAfter->getTimestamp() - $this->clock->now()->getTimestamp());
    }
}
