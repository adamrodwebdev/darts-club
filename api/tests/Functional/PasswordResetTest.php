<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Security\PurgeUnverifiedAccounts;
use App\Security\PurgeUnverifiedAccountsHandler;
use App\Tests\ApiTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/** « Mot de passe oublié » et comptes jamais confirmés. */
final class PasswordResetTest extends ApiTestCase
{
    private const NEW_PASSWORD = 'Nouveau-Mot-De-Passe-2026!';

    public function testAForgottenPasswordIsReplacedThroughAOneTimeLink(): void
    {
        $this->createUser('alice@example.com');

        $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com']);
        self::assertResponseStatusCodeSame(202);
        $query = $this->resetQuery();

        $this->resetPassword($query, self::NEW_PASSWORD);
        self::assertResponseStatusCodeSame(204);
        $this->login('alice@example.com', self::NEW_PASSWORD);
        self::assertResponseIsSuccessful();

        // Le lien ne sert qu'une fois : le mot de passe a changé depuis
        $this->resetPassword($query, 'Encore-Un-Autre-Mot-2026!');
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheAnswerIsTheSameForAnUnknownAddress(): void
    {
        $this->postJson('/api/auth/password/forgot', ['email' => 'inconnu@example.com']);

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(0);
    }

    public function testAResetLinkProvesTheAddressOfAnUnconfirmedAccount(): void
    {
        // Compte inscrit par quelqu'un d'autre : le titulaire de l'adresse le récupère
        $this->createUser('victim@example.com', verified: false);

        $this->postJson('/api/auth/password/forgot', ['email' => 'victim@example.com']);
        $this->resetPassword($this->resetQuery(), self::NEW_PASSWORD);
        self::assertResponseStatusCodeSame(204);

        $this->login('victim@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        $this->login('victim@example.com', self::NEW_PASSWORD);
        self::assertResponseIsSuccessful();
    }

    public function testAWeakNewPasswordOrATamperedLinkIsRefused(): void
    {
        $this->createUser('alice@example.com');
        $this->postJson('/api/auth/password/forgot', ['email' => 'alice@example.com']);
        $query = $this->resetQuery();

        $this->resetPassword($query, 'password1234');
        self::assertResponseStatusCodeSame(422);
        $this->resetPassword(str_replace('token=', 'token=x', $query), self::NEW_PASSWORD);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheAdministratorSendsTheLinkToAMemberWhoForgotTheirPassword(): void
    {
        $member = $this->createUser('alice@example.com');
        $president = $this->createUser('president@example.com');
        $president->grantRole(Role::President);
        $admin = $this->createUser('admin@example.com');
        $admin->grantRole(Role::Admin);
        $this->entityManager()->flush();
        $presidentSecret = $this->enableTwoFactor($president);
        $adminSecret = $this->enableTwoFactor($admin);
        $uri = "/api/admin/members/{$member->getId()->toRfc4122()}/password-link";

        // Réservé à l'administrateur
        $this->loginWithTwoFactor('president@example.com', $presidentSecret);
        $this->postJson($uri, []);
        self::assertResponseStatusCodeSame(403);

        $this->client->getCookieJar()->clear();
        $this->loginWithTwoFactor('admin@example.com', $adminSecret);
        $this->postJson($uri, []);
        self::assertResponseStatusCodeSame(202);
        self::assertSame(['member.password_link'], array_column($this->auditTrail(), 'action'));

        // Le lien part à l'adresse de l'adhérent, qui choisit son mot de passe
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'alice@example.com');
        self::assertEmailTextBodyContains($email, 'L\'administrateur du club t\'envoie ce lien');
        $this->resetPassword($this->resetQuery(), self::NEW_PASSWORD);
        self::assertResponseStatusCodeSame(204);
    }

    public function testAccountsNeverConfirmedAreDeletedAfterAWeek(): void
    {
        $this->createUser('old-pending@example.com', verified: false);
        $this->createUser('new-pending@example.com', verified: false);
        $this->createUser('old-member@example.com');
        $this->entityManager()->createQuery('UPDATE '.User::class.' u SET u.createdAt = :then WHERE u.email IN (:old)')
            ->setParameter('then', $this->now()->modify('-8 days'))
            ->setParameter('old', ['old-pending@example.com', 'old-member@example.com'])
            ->execute();

        $this->service(PurgeUnverifiedAccountsHandler::class, PurgeUnverifiedAccountsHandler::class)(new PurgeUnverifiedAccounts());

        $this->entityManager()->clear();
        $emails = array_map(static fn (User $user): string => $user->getEmail(), $this->entityManager()->getRepository(User::class)->findAll());
        sort($emails);
        self::assertSame(['new-pending@example.com', 'old-member@example.com'], $emails);
    }

    /** Paramètres du lien reçu (page /nouveau-mot-de-passe du site) */
    private function resetQuery(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        if (1 !== preg_match('#/nouveau-mot-de-passe\?(\S+)#', (string) $email->getTextBody(), $match)) {
            self::fail('Lien absent de l\'email.');
        }

        return $match[1];
    }

    private function resetPassword(string $query, string $password): void
    {
        $this->postJson('/api/auth/password/reset?'.$query, ['password' => $password]);
    }
}
