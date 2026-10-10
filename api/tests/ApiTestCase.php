<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
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
        $this->client = self::createClient();

        $this->service('cache.rate_limiter', CacheItemPoolInterface::class)->clear();

        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    protected function createUser(string $email, bool $verified = true): User
    {
        $hasher = $this->service(PasswordHasherFactoryInterface::class, PasswordHasherFactoryInterface::class)
            ->getPasswordHasher(User::class);

        $user = new User($email);
        $user->setPassword($hasher->hash(self::PASSWORD));
        if ($verified) {
            $user->markVerified();
        }

        $em = $this->entityManager();
        $em->persist($user);
        $em->flush();

        return $user;
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
