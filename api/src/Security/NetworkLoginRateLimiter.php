<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Limite des connexions (login_throttling) : 5 essais par compte et par origine, 25 par origine,
 * par quart d'heure. Même logique que le limiteur de Symfony, mais l'origine est le réseau /64
 * en IPv6 (ClientNetwork) : changer d'adresse dans son réseau ne donne pas de nouveaux essais.
 */
final class NetworkLoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $loginNetworkLimiter,
        private readonly RateLimiterFactoryInterface $loginAccountLimiter,
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    /** Une connexion réussie efface les essais du compte, jamais ceux de l'origine : ils bornent le nombre de comptes essayés. */
    public function reset(Request $request): void
    {
        $this->accountLimiter($request)->reset();
    }

    protected function getLimiters(Request $request): array
    {
        return [
            $this->loginNetworkLimiter->create($this->key((string) ClientNetwork::of($request))),
            $this->accountLimiter($request),
        ];
    }

    private function accountLimiter(Request $request): LimiterInterface
    {
        $username = $request->attributes->getString(SecurityRequestAttributes::LAST_USERNAME);
        $username = preg_match('//u', $username) ? mb_strtolower($username) : strtolower($username);

        return $this->loginAccountLimiter->create($this->key($username.'-'.ClientNetwork::of($request)));
    }

    /** Ni adresse ni identifiant en clair dans le stockage des limiteurs */
    private function key(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
