<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ApiTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class RegistrationTest extends ApiTestCase
{
    public function testRegistrationSendsVerificationLinkThatActivatesAccount(): void
    {
        $this->register('New.Member@Example.com', phone: '06 12 34 56 78');
        self::assertResponseStatusCodeSame(202);

        $user = $this->findUser('new.member@example.com');
        self::assertFalse($user->isVerified());
        self::assertSame('+33612345678', $user->getPhone());

        self::assertEmailCount(1);
        $this->client->request('GET', $this->verificationLink());
        self::assertResponseRedirects('http://localhost:5173/connexion?verify=success');

        $this->login('new.member@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
    }

    public function testExistingEmailGetsSameResponseAndNoticeEmail(): void
    {
        $this->createUser('member@example.com');

        $this->register('member@example.com');
        self::assertResponseStatusCodeSame(202);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, 'Tentative d\'inscription');
        self::assertCount(1, $this->users()->findAll());
    }

    public function testWeakPasswordIsRejected(): void
    {
        $this->register('new@example.com', password: 'password1234');
        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $this->register('new@example.com', phone: '123');
        self::assertResponseStatusCodeSame(422);
    }

    public function testTamperedVerificationLinkIsRefused(): void
    {
        $this->register('new@example.com');
        $this->client->request('GET', str_replace('signature=', 'signature=x', $this->verificationLink()));
        self::assertResponseRedirects('http://localhost:5173/connexion?verify=invalid');
        self::assertFalse($this->findUser('new@example.com')->isVerified());
    }

    public function testResendOnlyEmailsUnverifiedAccounts(): void
    {
        $this->createUser('pending@example.com', verified: false);
        $this->createUser('member@example.com');

        $this->postJson('/api/auth/verify/resend', ['email' => 'pending@example.com']);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);

        foreach (['member@example.com', 'unknown@example.com'] as $email) {
            $this->postJson('/api/auth/verify/resend', ['email' => $email]);
            self::assertResponseStatusCodeSame(202);
            self::assertEmailCount(0);
        }
    }

    public function testRegistrationIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->register("user{$i}@example.com");
            self::assertResponseStatusCodeSame(202);
        }

        $this->register('user6@example.com');
        self::assertResponseStatusCodeSame(429);
    }

    private function register(string $email, string $password = self::PASSWORD, ?string $phone = null): void
    {
        $this->postJson('/api/auth/register', array_filter(
            ['email' => $email, 'password' => $password, 'phone' => $phone],
            static fn (?string $v): bool => null !== $v,
        ));
    }

    private function verificationLink(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        $body = $email->getTextBody();
        self::assertIsString($body);
        if (1 !== preg_match('#http\S+/api/auth/verify\?\S+#', $body, $match)) {
            self::fail('Lien de vérification absent de l\'email.');
        }

        return $match[0];
    }

    private function findUser(string $email): User
    {
        $this->entityManager()->clear();
        $user = $this->users()->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function users(): UserRepository
    {
        return $this->service(UserRepository::class, UserRepository::class);
    }
}
