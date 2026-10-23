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
        $this->confirmAddress($this->verificationQuery(), self::PASSWORD);
        self::assertResponseIsSuccessful();
        self::assertSame('new.member@example.com', $this->json()['email']);

        $this->login('new.member@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
    }

    public function testConfirmingNeedsThePasswordChosenAtSignUp(): void
    {
        $this->register('new@example.com');
        $query = $this->verificationQuery();

        // Le titulaire de l'adresse, s'il n'a pas choisi ce mot de passe, n'active pas le compte d'un autre
        $this->confirmAddress($query, 'Un-Autre-Mot-De-Passe-2026');
        self::assertResponseStatusCodeSame(422);
        self::assertFalse($this->findUser('new@example.com')->isVerified());

        // Un lien cliqué tel quel mène à la page qui demande le mot de passe
        $this->client->request('GET', 'http://localhost:5173/api/auth/verify?'.$query);
        self::assertResponseRedirects('http://localhost:5173/confirmer-email?'.$query);
    }

    public function testAnUnconfirmedAddressCanBeRegisteredAgainWithANewPassword(): void
    {
        // Quelqu'un inscrit l'adresse d'un autre avec son propre mot de passe…
        $this->register('victim@example.com', password: 'Mot-De-Passe-Intrus-2026');
        $this->client->setServerParameter('REMOTE_ADDR', '198.51.100.20');
        // … le titulaire s'inscrit à son tour : son mot de passe remplace celui de l'intrus
        $this->register('victim@example.com');
        self::assertResponseStatusCodeSame(202);
        $query = $this->verificationQuery();

        $this->confirmAddress($query, 'Mot-De-Passe-Intrus-2026');
        self::assertResponseStatusCodeSame(422);
        $this->confirmAddress($query, self::PASSWORD);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->users()->findAll());
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
        $this->confirmAddress(str_replace('signature=', 'signature=x', $this->verificationQuery()), self::PASSWORD);
        self::assertResponseStatusCodeSame(422);
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

    /** Paramètres signés du lien reçu (page /confirmer-email du site) */
    private function verificationQuery(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        $body = $email->getTextBody();
        self::assertIsString($body);
        if (1 !== preg_match('#http\S+/confirmer-email\?(\S+)#', $body, $match)) {
            self::fail('Lien de vérification absent de l\'email.');
        }

        return $match[1];
    }

    /** La page renvoie les paramètres du lien et le mot de passe saisi */
    private function confirmAddress(string $query, string $password): void
    {
        $this->client->request(
            'POST',
            'http://localhost:5173/api/auth/verify?'.$query,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN],
            content: json_encode(['password' => $password], \JSON_THROW_ON_ERROR),
        );
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
