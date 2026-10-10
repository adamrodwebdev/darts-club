<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class AuthTest extends WebTestCase
{
    private const PASSWORD = 'Tr1ple-20-Bullseye!';
    private const FRONT_ORIGIN = 'http://localhost:5173';

    private KernelBrowser $client;

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

    public function testVerifiedUserCanLogInAndFetchProfile(): void
    {
        $this->createUser('member@example.com', verified: true);

        $this->login('member@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
        self::assertSame('member@example.com', $this->json()['email']);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_MEMBER'], $this->json()['roles']);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser('member@example.com', verified: true);

        $this->login('member@example.com', 'wrong');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUnverifiedUserCannotLogIn(): void
    {
        $this->createUser('new@example.com', verified: false);

        $this->login('new@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Adresse email non vérifiée.', $this->json()['error']);
    }

    public function testProfileRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testCrossOriginWriteIsBlocked(): void
    {
        $this->createUser('member@example.com', verified: true);

        $this->login('member@example.com', self::PASSWORD, origin: 'https://evil.example');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $this->createUser('member@example.com', verified: true);

        for ($i = 0; $i < 5; ++$i) {
            $this->login('member@example.com', 'wrong');
        }

        $this->login('member@example.com', self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        $error = $this->json()['error'];
        self::assertIsString($error);
        self::assertStringContainsString('Too many failed login attempts', $error);
    }

    public function testLogoutEndsSession(): void
    {
        $this->createUser('member@example.com', verified: true);
        $this->login('member@example.com', self::PASSWORD);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testPasswordIsHashedWithArgon2id(): void
    {
        $user = $this->createUser('member@example.com', verified: true);

        self::assertStringStartsWith('$argon2id$', $user->getPassword());
    }

    private function createUser(string $email, bool $verified): User
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

    private function login(string $email, string $password, string $origin = self::FRONT_ORIGIN): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $origin],
            content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function entityManager(): EntityManagerInterface
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
    private function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
