<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Preference\Language;
use App\Tests\ApiTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/** Langue de l'API : réponses selon Accept-Language, emails selon la langue du compte. */
final class LocalizationTest extends ApiTestCase
{
    private const ENGLISH = ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9'];

    public function testLoginFailureIsTranslatedAndCarriesAStableCode(): void
    {
        $this->createUser('new@example.com', verified: false);

        $this->login('new@example.com', self::PASSWORD);
        self::assertSame(['error' => 'Adresse email non vérifiée.', 'code' => 'email_not_verified'], $this->json());

        $this->requestJson('POST', '/api/auth/login', ['email' => 'new@example.com', 'password' => self::PASSWORD], self::ENGLISH);
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Email address not verified.', 'code' => 'email_not_verified'], $this->json());
        self::assertResponseHeaderSame('Content-Language', 'en');
    }

    public function testDomainErrorsAreTranslatedWithTheirParameters(): void
    {
        $this->createUser('member@example.com');
        $this->login('member@example.com', self::PASSWORD);

        $this->requestJson('PUT', '/api/me/notification-preferences', ['preferences' => ['concert' => []]], self::ENGLISH);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Unknown category: “concert”.', $this->json()['detail'] ?? null);

        $this->requestJson('PUT', '/api/me/notification-preferences', ['preferences' => ['concert' => []]]);
        self::assertSame('Catégorie inconnue : « concert ».', $this->json()['detail'] ?? null);
    }

    public function testDisplayPreferencesAreSavedOnTheAccount(): void
    {
        $this->createUser('member@example.com');
        $this->login('member@example.com', self::PASSWORD);
        self::assertSame(['language' => 'fr', 'theme' => 'system'], $this->json()['preferences']);

        $this->requestJson('PUT', '/api/me/preferences', ['language' => 'en', 'theme' => 'dark']);
        self::assertResponseIsSuccessful();
        self::assertSame(['language' => 'en', 'theme' => 'dark'], $this->json()['preferences']);

        $this->requestJson('PUT', '/api/me/preferences', ['language' => 'de', 'theme' => 'neon']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRegistrationKeepsTheVisitorLanguageForEmails(): void
    {
        $this->requestJson('POST', '/api/auth/register', ['email' => 'visitor@example.com', 'password' => self::PASSWORD], self::ENGLISH);
        self::assertResponseStatusCodeSame(202);

        $this->entityManager()->clear();
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'visitor@example.com']);
        self::assertNotNull($user);
        self::assertSame(Language::English, $user->language());

        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('Confirm your email address', $email->getSubject());
        self::assertStringContainsString('Welcome to the club!', (string) $email->getTextBody());
    }

    /**
     * @param array<mixed>          $payload
     * @param array<string, string> $server
     */
    private function requestJson(string $method, string $uri, array $payload, array $server = []): void
    {
        $this->client->request($method, $uri, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => self::FRONT_ORIGIN,
            ...$server,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
