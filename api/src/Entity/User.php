<?php

namespace App\Entity;

use App\Doctrine\EncryptedStringType;
use App\Enum\Role;
use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Event\UserRegistered;
use App\Notification\NotificationPreferences;
use App\Repository\UserRepository;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use App\ValueObject\PhoneNumber;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, RecordsDomainEvents, TwoFactorInterface, BackupCodeInterface
{
    use DomainEventsTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /** Format E.164 */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    /**
     * Rôles accordés en plus de ROLE_MEMBER.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** Hash Argon2id ; vide pour un compte créé via OAuth (aucun mot de passe utilisable). */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private bool $verified = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var array<string, list<string>> */
    #[ORM\Column(type: Types::JSON)]
    private array $notificationPreferences = [];

    #[ORM\Column(length: DisplayName::MAX, nullable: true)]
    private ?string $displayName = null;

    /** Identifiant du fichier dans AvatarStorage */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $avatar = null;

    /** Graine TOTP (base32), chiffrée en base */
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column]
    private bool $totpEnabled = false;

    /**
     * Empreintes SHA-256 des codes de secours non utilisés.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $backupCodes = [];

    private function __construct(EmailAddress $email)
    {
        $this->id = Uuid::v7();
        $this->email = $email->value;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Inscription classique : le compte reste inactif jusqu'à la vérification de l'email. */
    public static function register(EmailAddress $email, string $hashedPassword, ?PhoneNumber $phone = null): self
    {
        $user = new self($email);
        $user->password = $hashedPassword;
        $user->phone = $phone?->e164;
        $user->recordThat(new UserRegistered($user->id));

        return $user;
    }

    /** Inscription via un fournisseur OAuth qui a déjà vérifié l'email. */
    public static function registerViaOAuth(EmailAddress $email): self
    {
        $user = new self($email);
        $user->verified = true;
        $user->recordThat(new UserRegistered($user->id, viaOAuth: true));

        return $user;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getEmail(): string
    {
        return $this->email;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function changePhone(?PhoneNumber $phone): void
    {
        $this->phone = $phone?->e164;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, Role::Member->value]));
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role->value, $this->getRoles(), true);
    }

    public function grantRole(Role $role): void
    {
        if (Role::Member !== $role && !\in_array($role->value, $this->roles, true)) {
            $this->roles[] = $role->value;
        }
    }

    public function revokeRole(Role $role): void
    {
        $this->roles = array_values(array_filter($this->roles, static fn (string $r): bool => $r !== $role->value));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function markVerified(): void
    {
        $this->verified = true;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function displayName(): DisplayName
    {
        return null !== $this->displayName
            ? DisplayName::fromString($this->displayName)
            : DisplayName::fromEmail(EmailAddress::fromString($this->email));
    }

    public function rename(DisplayName $name): void
    {
        $this->displayName = $name->value;
    }

    public function avatar(): ?string
    {
        return $this->avatar;
    }

    /** @return string|null identifiant de l'ancien avatar, à supprimer du stockage */
    public function changeAvatar(?string $avatarId): ?string
    {
        $previous = $this->avatar;
        $this->avatar = $avatarId;

        return $previous;
    }

    public function hasPassword(): bool
    {
        return '' !== $this->password;
    }

    /** Étape 1 : graine générée, pas encore active tant qu'un code n'a pas été confirmé. */
    public function startTotpEnrollment(string $secret): void
    {
        $this->totpSecret = $secret;
        $this->totpEnabled = false;
        $this->backupCodes = [];
    }

    /**
     * Étape 2 : l'adhérent a saisi un code valide.
     *
     * @param list<string> $backupCodes codes en clair, seules leurs empreintes sont stockées
     */
    public function enableTotp(array $backupCodes): void
    {
        if (null === $this->totpSecret) {
            throw new \LogicException('Aucune configuration TOTP en cours.');
        }

        $this->totpEnabled = true;
        $this->backupCodes = array_map(self::hashBackupCode(...), $backupCodes);
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
        $this->totpEnabled = false;
        $this->backupCodes = [];
    }

    public function isBoardMember(): bool
    {
        foreach ($this->roles as $role) {
            if (Role::tryFrom($role)?->isBoard()) {
                return true;
            }
        }

        return false;
    }

    /** Politique du club : 2FA obligatoire pour le bureau. */
    public function mustSetUpTwoFactor(): bool
    {
        return $this->isBoardMember() && !$this->hasTwoFactor();
    }

    public function hasTwoFactor(): bool
    {
        return $this->totpEnabled && null !== $this->totpSecret;
    }

    public function remainingBackupCodes(): int
    {
        return \count($this->backupCodes);
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->hasTwoFactor();
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    /** Disponible dès l'enrôlement, pour vérifier le premier code. */
    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return null !== $this->totpSecret
            ? new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6)
            : null;
    }

    public function isBackupCode(string $code): bool
    {
        return \in_array(self::hashBackupCode($code), $this->backupCodes, true);
    }

    public function invalidateBackupCode(string $code): void
    {
        $hash = self::hashBackupCode($code);
        $this->backupCodes = array_values(array_filter($this->backupCodes, static fn (string $h): bool => $h !== $hash));
    }

    private static function hashBackupCode(string $code): string
    {
        return hash('sha256', strtolower(str_replace([' ', '-'], '', $code)));
    }

    public function notificationPreferences(): NotificationPreferences
    {
        return NotificationPreferences::fromArray($this->notificationPreferences);
    }

    public function changeNotificationPreferences(NotificationPreferences $preferences): void
    {
        $this->notificationPreferences = $preferences->toArray();
    }

    /**
     * Ne stocke jamais le hash en session : seule une empreinte sert à
     * détecter un changement de mot de passe (Symfony 7.3+).
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $key => $value) {
            $data[(string) $key] = $value;
        }
        // Jamais de secret 2FA ni d'empreintes de codes de secours en session
        unset(
            $data["\0".self::class."\0domainEvents"],
            $data["\0".self::class."\0totpSecret"],
            $data["\0".self::class."\0backupCodes"],
        );
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
