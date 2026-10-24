<?php

namespace App\Tests;

use App\Entity\AuditLog;
use App\Entity\User;
use App\ValueObject\EmailAddress;
use App\ValueObject\NewAccount;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use OTPHP\TOTP;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/** Client HTTP + base de données remise à zéro à chaque test. */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'Tr1ple-20-Bullseye!';
    protected const FRONT_ORIGIN = 'http://localhost:5173';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // Request::create() annonce « en-us » par défaut : les parcours sont écrits en français
        $this->client = self::createClient(server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);

        $this->service('cache.rate_limiter', CacheItemPoolInterface::class)->clear();

        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    /** Instant présent de l'application (horloge figée des tests) */
    protected function now(): \DateTimeImmutable
    {
        return $this->service('clock', ClockInterface::class)->now();
    }

    protected function createUser(string $email, bool $verified = true): User
    {
        $hasher = $this->service(PasswordHasherFactoryInterface::class, PasswordHasherFactoryInterface::class)
            ->getPasswordHasher(User::class);

        $user = User::register(new NewAccount(EmailAddress::fromString($email)), $hasher->hash(self::PASSWORD), $this->now());
        // Donnée de test : pas d'email de vérification pour les comptes préexistants
        $user->releaseEvents();
        if ($verified) {
            $user->markVerified();
        }

        $em = $this->entityManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Active la 2FA directement en base (donnée de test).
     *
     * @param list<string> $backupCodes
     *
     * @return non-empty-string graine TOTP
     */
    protected function enableTwoFactor(User $user, array $backupCodes = ['aaaaa-11111']): string
    {
        $secret = TOTP::generate()->getSecret();
        $user->startTotpEnrollment($secret);
        $user->enableTotp($backupCodes);
        $this->entityManager()->flush();

        return $secret;
    }

    protected function totpCode(string $secret): string
    {
        if ('' === $secret) {
            self::fail('Graine TOTP vide.');
        }

        // Même horloge que l'application (figée en test)
        $now = $this->service('clock', ClockInterface::class)->now();

        return TOTP::createFromSecret($secret)->at(max(0, $now->getTimestamp()));
    }

    /** Lecture typée d'une valeur imbriquée de la réponse JSON (« status », « id »…). */
    protected function dig(mixed $data, string|int ...$keys): mixed
    {
        foreach ($keys as $key) {
            self::assertIsArray($data);
            self::assertArrayHasKey($key, $data);
            $data = $data[$key];
        }

        return $data;
    }

    /** Comme dig(), en exigeant une chaîne. */
    protected function digString(mixed $data, string|int ...$keys): string
    {
        $value = $this->dig($data, ...$keys);
        self::assertIsString($value);

        return $value;
    }

    /** Connexion complète d'un compte protégé par 2FA. */
    protected function loginWithTwoFactor(string $email, string $secret): void
    {
        $this->login($email, self::PASSWORD);
        self::assertResponseStatusCodeSame(202);
        $this->postJson('/api/auth/2fa/check', ['code' => $this->totpCode($secret)]);
        self::assertResponseIsSuccessful();
    }

    /** @param array<mixed> $payload */
    protected function postJson(string $uri, array $payload, string $origin = self::FRONT_ORIGIN): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $origin],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    protected function login(string $email, string $password, string $origin = self::FRONT_ORIGIN): void
    {
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password], $origin);
    }

    /** @return array<mixed> */
    protected function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** @return list<array<string, mixed>> journal d'audit, du plus ancien au plus récent */
    protected function auditTrail(): array
    {
        $logs = $this->entityManager()->getRepository(AuditLog::class)->findBy([], ['id' => 'ASC']);

        return array_map(static fn (AuditLog $log): array => $log->toArray(), $logs);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class, EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
