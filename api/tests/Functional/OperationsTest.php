<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** Exploitation : sonde de disponibilité et amorçage du premier président. */
final class OperationsTest extends ApiTestCase
{
    public function testHealthProbeIsPublicAndRevealsNothing(): void
    {
        $this->client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], $this->json());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testOnlyConfiguredOAuthProvidersAreOffered(): void
    {
        $this->client->request('GET', '/api/auth/oauth/providers');

        self::assertResponseIsSuccessful();
        // .env : identifiants vides, aucun bouton proposé
        self::assertSame(['providers' => []], $this->json());
    }

    public function testFirstPresidentIsNamedFromTheCommandLine(): void
    {
        $this->createUser('bureau@example.com');
        $this->createUser('pending@example.com', verified: false);
        $command = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:member:grant-role'));

        self::assertSame(Command::INVALID, $command->execute(['email' => 'bureau@example.com', 'role' => 'roi']));
        self::assertSame(Command::FAILURE, $command->execute(['email' => 'pending@example.com', 'role' => 'president']));
        self::assertSame(Command::SUCCESS, $command->execute(['email' => 'bureau@example.com', 'role' => 'president']));

        $this->entityManager()->clear();
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'bureau@example.com']);
        self::assertNotNull($user);
        self::assertContains('ROLE_PRESIDENT', $user->getRoles());
        // Bureau : une clé d'accès avant les fonctions de la présidence
        self::assertTrue($user->mustSetUpPasskey());
    }
}
