<?php

namespace App\Controller;

use App\Security\OAuthAuthenticator;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/oauth/{provider}', name: 'auth_oauth_', requirements: ['provider' => 'google|discord'])]
final class OAuthController extends AbstractController
{
    private const SCOPES = [
        'google' => ['openid', 'email', 'profile'],
        'discord' => ['identify', 'email'],
    ];

    #[Route('', name: 'start', methods: ['GET'])]
    public function start(string $provider, ClientRegistry $clients): RedirectResponse
    {
        \assert(\in_array($provider, OAuthAuthenticator::PROVIDERS, true));

        return $clients->getClient($provider)->redirect(self::SCOPES[$provider], []);
    }

    /** Intercepté par OAuthAuthenticator. */
    #[Route('/check', name: 'check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('Intercepté par OAuthAuthenticator.');
    }
}
