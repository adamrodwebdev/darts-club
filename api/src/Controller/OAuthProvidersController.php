<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Fournisseurs OAuth réellement configurés : le front n'affiche que ceux-là. */
final class OAuthProvidersController
{
    public function __construct(
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_ID)%')]
        private readonly string $googleClientId,
        #[Autowire('%env(OAUTH_DISCORD_CLIENT_ID)%')]
        private readonly string $discordClientId,
    ) {
    }

    #[Route('/api/auth/oauth/providers', name: 'auth_oauth_providers', methods: ['GET'], format: 'json')]
    public function __invoke(): JsonResponse
    {
        $providers = array_keys(array_filter(['google' => $this->googleClientId, 'discord' => $this->discordClientId], static fn (string $id): bool => '' !== $id));

        return new JsonResponse(['providers' => $providers], headers: ['Cache-Control' => 'public, max-age=300']);
    }
}
