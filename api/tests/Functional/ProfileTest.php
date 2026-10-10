<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\UserRepository;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfileTest extends ApiTestCase
{
    private const NEW_PASSWORD = 'Doub1e-Top-Checkout!';

    public function testProfileDefaultsDisplayNameToEmailLocalPart(): void
    {
        $this->loginAs('jean.dupont@example.com');

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame('jean.dupont', $this->json()['displayName']);
        self::assertNull($this->json()['avatarUrl']);
    }

    public function testUpdateDisplayNameAndPhone(): void
    {
        $this->loginAs('alice@example.com');

        $this->request('PATCH', '/api/me', ['displayName' => '  Alice   la Flèche ', 'phone' => '06 12 34 56 78']);
        self::assertResponseIsSuccessful();
        self::assertSame('Alice la Flèche', $this->json()['displayName']);
        self::assertSame('+33612345678', $this->json()['phone']);

        $this->request('PATCH', '/api/me', ['phone' => '']);
        self::assertNull($this->json()['phone']);
        self::assertSame('Alice la Flèche', $this->json()['displayName'], 'Champ absent = inchangé');

        $this->request('PATCH', '/api/me', ['displayName' => '<script>']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordKeepsCurrentSession(): void
    {
        $this->loginAs('alice@example.com');

        $this->request('POST', '/api/me/password', ['currentPassword' => 'wrong', 'newPassword' => self::NEW_PASSWORD]);
        self::assertResponseStatusCodeSame(422);

        $this->request('POST', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => self::NEW_PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->login('alice@example.com', self::NEW_PASSWORD);
        self::assertResponseIsSuccessful();
    }

    public function testAvatarIsReencodedAndServedToMembersOnly(): void
    {
        $alice = $this->loginAs('alice@example.com');

        $this->uploadAvatar('avatar.jpg');
        self::assertResponseIsSuccessful();
        $url = $this->json()['avatarUrl'];
        self::assertIsString($url);

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        $size = getimagesize($this->streamedFile());
        self::assertIsArray($size);
        self::assertSame([256, 256], [$size[0], $size[1]]);

        $this->client->request('POST', '/api/auth/logout', server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN]);
        $this->client->request('GET', '/api/users/'.$alice->getId()->toRfc4122().'/avatar');
        self::assertResponseStatusCodeSame(401);
    }

    public function testNonImageUploadIsRejected(): void
    {
        $this->loginAs('alice@example.com');

        $this->uploadAvatar('not-an-image.jpg');
        self::assertResponseStatusCodeSame(422);
    }

    public function testExportContainsPersonalData(): void
    {
        $alice = $this->loginAs('alice@example.com');
        $this->service(MemberNotifier::class, MemberNotifier::class)
            ->notify(new NotificationContent(Category::Meeting, 'AG', 'Mardi'), Recipients::users($alice->getId()));

        $this->client->request('GET', '/api/me/export');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename="mes-donnees-skull-darts-71.json"');
        $data = $this->json();
        self::assertIsArray($data['account']);
        self::assertSame('alice@example.com', $data['account']['email']);
        self::assertIsArray($data['notifications']);
        self::assertCount(1, $data['notifications']);
    }

    public function testDeleteAccountRequiresConfirmationAndPassword(): void
    {
        $this->loginAs('alice@example.com');
        $this->uploadAvatar('avatar.jpg');

        $this->request('DELETE', '/api/me', ['confirmation' => 'oui', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(422);

        $this->request('DELETE', '/api/me', ['confirmation' => 'SUPPRIMER', 'password' => 'wrong']);
        self::assertResponseStatusCodeSame(422);

        $this->request('DELETE', '/api/me', ['confirmation' => 'SUPPRIMER', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        $this->entityManager()->clear();
        self::assertSame(0, $this->service(UserRepository::class, UserRepository::class)->count([]));

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    private function loginAs(string $email): User
    {
        $user = $this->createUser($email);
        $this->login($email, self::PASSWORD);
        self::assertResponseIsSuccessful();

        return $user;
    }

    /** @param array<mixed> $payload */
    private function request(string $method, string $uri, array $payload): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => self::FRONT_ORIGIN], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function uploadAvatar(string $fixture): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'avatar');
        self::assertIsString($copy);
        copy(__DIR__.'/../Fixtures/'.$fixture, $copy);

        $this->client->request(
            'POST',
            '/api/me/avatar',
            files: ['avatar' => new UploadedFile($copy, $fixture, test: true)],
            server: ['HTTP_ORIGIN' => self::FRONT_ORIGIN],
        );
    }

    /** Chemin du fichier servi par la dernière BinaryFileResponse. */
    private function streamedFile(): string
    {
        $response = $this->client->getResponse();
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);

        return $response->getFile()->getPathname();
    }
}
