<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Enum\Role;
use App\ValueObject\EmailAddress;
use App\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisteredUserIsUnverifiedMember(): void
    {
        $user = User::register(EmailAddress::fromString(' Bob@Example.COM '), 'hash', PhoneNumber::fromInput('06 12 34 56 78'));

        self::assertSame('bob@example.com', $user->getEmail());
        self::assertSame('+33612345678', $user->getPhone());
        self::assertFalse($user->isVerified());
        self::assertSame([Role::Member->value], $user->getRoles());
    }

    public function testOAuthUserIsVerifiedWithoutPassword(): void
    {
        $user = User::registerViaOAuth(EmailAddress::fromString('bob@example.com'));

        self::assertTrue($user->isVerified());
        self::assertSame('', $user->getPassword());
    }

    public function testRolesCanBeGrantedAndRevokedButMemberIsPermanent(): void
    {
        $user = User::registerViaOAuth(EmailAddress::fromString('bob@example.com'));

        $user->grantRole(Role::Treasurer);
        $user->grantRole(Role::Treasurer);
        self::assertSame([Role::Treasurer->value, Role::Member->value], $user->getRoles());

        $user->revokeRole(Role::Treasurer);
        $user->revokeRole(Role::Member);
        self::assertSame([Role::Member->value], $user->getRoles());
        self::assertTrue($user->hasRole(Role::Member));
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailAddress::fromString('not-an-email');
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneNumber::fromInput('123');
    }

    public function testInternationalPhoneKeepsItsCountryCode(): void
    {
        self::assertSame('+32470123456', (string) PhoneNumber::fromInput('+32 470 12 34 56'));
    }
}
