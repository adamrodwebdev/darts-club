<?php

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\Role;
use App\Security\OAuthProfile;
use App\Security\OAuthUserResolver;
use App\Tests\ApiTestCase;

/** Nouveaux comptes validés par le président ou l'administrateur avant l'espace membres. */
final class MemberApprovalTest extends ApiTestCase
{
    public function testAnAccountWaitingForApprovalSeesOnlyItsOwnAccount(): void
    {
        $this->createUser('newbie@example.com', approved: false);
        $this->login('newbie@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['approved']);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        foreach (['/api/events', '/api/members?q=a', '/api/conversations', '/api/notifications'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403, $uri);
        }
    }

    public function testThePresidencyIsToldAndOpensTheMembersArea(): void
    {
        $president = $this->createUser('president@example.com');
        $president->grantRole(Role::President);
        $this->entityManager()->flush();
        $secret = $this->enableTwoFactor($president);

        // Adresse confirmée par Google : le président en est prévenu dans le site
        $resolver = $this->service(OAuthUserResolver::class, OAuthUserResolver::class);
        $newbie = $resolver->resolve(new OAuthProfile(provider: 'google', subject: 'google-subject-9', email: 'newbie@example.com', emailVerified: true));
        $notifications = $this->entityManager()->getRepository(Notification::class)->findAll();
        self::assertCount(1, $notifications);
        self::assertStringContainsString('Nouveau compte à valider', $notifications[0]->toArray()['title']);

        $this->loginWithTwoFactor('president@example.com', $secret);
        $this->client->request('GET', '/api/admin/members?q=newbie');
        self::assertFalse($this->dig($this->json(), 'items', 0, 'approved'));

        $this->postJson("/api/admin/members/{$newbie->getId()->toRfc4122()}/approve", []);
        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailSubjectContains($email, 'ton compte est validé');
        self::assertSame(['member.approve'], array_column($this->auditTrail(), 'action'));

        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(User::class, $newbie->getId());
        self::assertTrue($stored?->isApproved());
        self::assertContains('ROLE_MEMBER', $stored->getRoles());
    }

    public function testAMemberCannotApproveOthers(): void
    {
        $this->createUser('alice@example.com');
        $newbie = $this->createUser('newbie@example.com', approved: false);
        $this->login('alice@example.com', self::PASSWORD);

        $this->postJson("/api/admin/members/{$newbie->getId()->toRfc4122()}/approve", []);
        self::assertResponseStatusCodeSame(403);
    }
}
