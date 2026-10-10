<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Security\OAuthUserResolver;
use App\Tests\ApiTestCase;

/** Corrections issues de la revue de sécurité avant la mise en production. */
final class SecurityHardeningTest extends ApiTestCase
{
    public function testTwoFactorAttemptsAreCappedPerAccountAcrossNewLogins(): void
    {
        $alice = $this->createUser('alice@example.com');
        $secret = $this->enableTwoFactor($alice);

        // Attaquant qui change d'IP et de session à chaque connexion : 3 × 4 codes faux,
        // sous le plafond par session et par IP, au-dessus du plafond par compte (10/h)
        $statuses = [];
        for ($login = 0; $login < 3; ++$login) {
            $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.'.($login + 1));
            $this->login('alice@example.com', self::PASSWORD);
            for ($i = 0; $i < 4; ++$i) {
                $this->postJson('/api/auth/2fa/check', ['code' => '000000']);
                $statuses[] = $this->client->getResponse()->getStatusCode();
            }
        }
        // 11e code faux : 429 et session fermée (les requêtes suivantes ne sont plus authentifiées)
        self::assertSame(429, $statuses[10]);

        // Une nouvelle connexion ne redonne pas d'essais, même avec le bon code ensuite
        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.99');
        $this->login('alice@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(429);
        $this->postJson('/api/auth/2fa/check', ['code' => $this->totpCode($secret)]);
        self::assertNotSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testWrongPasswordRevealsNothingAboutTheAccountState(): void
    {
        $this->createUser('pending@example.com', verified: false);

        $this->login('pending@example.com', 'mauvais-mot-de-passe');
        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_credentials', $this->json()['code']);
    }

    public function testOAuthLinkingDropsThePasswordOfAnUnconfirmedAccount(): void
    {
        // Un tiers a inscrit l'adresse de la victime sans pouvoir la confirmer
        $this->createUser('victim@example.com', verified: false);

        $resolver = $this->service(OAuthUserResolver::class, OAuthUserResolver::class);
        $user = $resolver->resolve('google', 'google-subject-1', 'victim@example.com', true);

        self::assertTrue($user->isVerified());
        self::assertFalse($user->hasPassword());
        $this->login('victim@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyAnAdministratorCanReactivateAnAdministrator(): void
    {
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $admin->suspend('Test');
        $president = $this->createUser('president@example.com');
        $president->grantRole(Role::President);
        $this->entityManager()->flush();
        $this->loginWithTwoFactor('president@example.com', $this->enableTwoFactor($president));

        $this->postJson("/api/admin/members/{$admin->getId()->toRfc4122()}/reactivate", []);
        self::assertResponseStatusCodeSame(422);

        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(User::class, $admin->getId());
        self::assertTrue($stored?->isSuspended());
    }

    public function testBoardMemberWithoutTwoFactorCannotBrowseTheMemberDirectory(): void
    {
        $treasurer = $this->createUser('treasurer@example.com');
        $treasurer->grantRole(Role::Treasurer);
        $this->entityManager()->flush();
        $this->login('treasurer@example.com', self::PASSWORD);

        $this->client->request('GET', '/api/members?q=a');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('two_factor_setup_required', $this->json()['code']);
    }
}
