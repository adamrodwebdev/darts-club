<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sonde de disponibilité (UptimeRobot, Docker) : application et base joignables.
 * Aucune information interne exposée.
 */
final class HealthController
{
    #[Route('/api/health', name: 'health', methods: ['GET', 'HEAD'], format: 'json')]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE, ['Cache-Control' => 'no-store']);
        }

        return new JsonResponse(['status' => 'ok'], headers: ['Cache-Control' => 'no-store']);
    }
}
