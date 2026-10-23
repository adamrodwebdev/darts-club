<?php

namespace App\Tests\Functional;

use App\Security\OAuthProfile;
use App\Security\OAuthUserResolver;
use App\Security\TwoFactor\BackupCodes;
use App\Tests\ApiTestCase;

/** Revue d'octobre 2026 : double authentification et alertes du compte. */
final class TwoFactorHardeningTest extends ApiTestCase
{
    public function testATotpCodeOpensOneSessionOnly(): void
    {
        $alice = $this->createUser('alice@example.com');
        $secret = $this->enableTwoFactor($alice);
        $this->loginWithTwoFactor('alice@example.com', $secret);
        $code = $this->totpCode($secret);

        // Code intercepté, rejoué depuis une autre session dans la même minute
        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(202);
        $this->postJson('/api/auth/2fa/check', ['code' => $code]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testBackupCodesFromBeforeTheReviewStillWorkOnce(): void
    {
        $alice = $this->createUser('alice@example.com');
        $this->enableTwoFactor($alice, []);
        $alice->enableTotp([hash('sha256', 'abcde12345')]);
        $this->entityManager()->flush();

        foreach ([200, 401] as $expected) {
            $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
            $this->login('alice@example.com', self::PASSWORD);
            $this->postJson('/api/auth/2fa/check', ['code' => 'abcde-12345']);
            self::assertResponseStatusCodeSame($expected);
        }
    }

    public function testNewBackupCodesCarryEightyBitsAndAKeyedHash(): void
    {
        $backupCodes = $this->service(BackupCodes::class, BackupCodes::class);
        $codes = $backupCodes->generate();

        self::assertCount(BackupCodes::COUNT, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[a-z2-7]{4}(-[a-z2-7]{4}){3}$/', $code);
        }
        self::assertNotSame(hash('sha256', str_replace('-', '', $codes[0])), $backupCodes->hashes([$codes[0]])[0]);
    }

    public function testEnablingTwoFactorClosesTheOtherSessions(): void
    {
        $this->createUser('alice@example.com');
        $jar = $this->client->getCookieJar();

        // Une session déjà ouverte ailleurs (mot de passe fuité)…
        $this->login('alice@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
        $intruder = $jar->all();

        // … puis Alice active la 2FA depuis sa propre session
        $jar->clear();
        $this->login('alice@example.com', self::PASSWORD);
        $this->postJson('/api/me/2fa/setup', []);
        $secret = $this->digString($this->json(), 'secret');
        $this->postJson('/api/me/2fa/confirm', ['code' => $this->totpCode($secret)]);
        self::assertResponseIsSuccessful();
        $alice = $jar->all();

        $jar->clear();
        foreach ($intruder as $cookie) {
            $jar->set($cookie);
        }
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        self::assertSame('session_ended', $this->json()['code']);

        $jar->clear();
        foreach ($alice as $cookie) {
            $jar->set($cookie);
        }
        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testLinkingAProviderWarnsTheAccountHolder(): void
    {
        $this->createUser('alice@example.com');

        $resolver = $this->service(OAuthUserResolver::class, OAuthUserResolver::class);
        $resolver->resolve(new OAuthProfile(provider: 'google', subject: 'google-subject-7', email: 'alice@example.com', emailVerified: true));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, 'Connexion Google ajoutée à ton compte');
    }
}
