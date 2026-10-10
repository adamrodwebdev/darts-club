<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

final class OAuthAuthenticator extends OAuth2Authenticator
{
    public const PROVIDERS = ['google', 'discord'];

    public function __construct(
        private readonly ClientRegistry $clients,
        private readonly OAuthUserResolver $resolver,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'auth_oauth_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $provider = $request->attributes->getString('provider');
        $client = $this->clients->getClient($provider);
        $owner = $client->fetchUserFromToken($this->fetchAccessToken($client));
        $subject = self::subject($owner->getId());
        [$email, $verified] = self::extractEmail($owner);

        // Identifiant du badge : jamais le jeton d'accès (journaux, limiteur)
        return new SelfValidatingPassport(new UserBadge(
            $provider.':'.$subject,
            fn () => $this->resolver->resolve($provider, $subject, $email, $verified),
        ));
    }

    private static function subject(mixed $id): string
    {
        if (!\is_string($id) && !\is_int($id)) {
            throw new CustomUserMessageAuthenticationException('Identité OAuth invalide.');
        }

        return (string) $id;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->frontUrl('/?login=oauth'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new RedirectResponse($this->frontUrl('/connexion?oauth=failed'));
    }

    /** @return array{?string, bool} */
    private static function extractEmail(ResourceOwnerInterface $owner): array
    {
        return match (true) {
            $owner instanceof GoogleUser => [$owner->getEmail(), $owner->isEmailTrustworthy()],
            $owner instanceof DiscordResourceOwner => [self::stringOrNull($owner->getEmail()), true === $owner->getVerified()],
            default => [null, false],
        };
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private function frontUrl(string $path): string
    {
        return rtrim($this->frontendUrl, '/').$path;
    }
}
