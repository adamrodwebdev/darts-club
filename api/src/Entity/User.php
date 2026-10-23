<?php

namespace App\Entity;

use App\Doctrine\EncryptedStringType;
use App\Enum\Role;
use App\Event\DomainEventsTrait;
use App\Event\MemberApproved;
use App\Event\MemberAwaitingApproval;
use App\Event\ProviderLinked;
use App\Event\RecordsDomainEvents;
use App\Event\UserRegistered;
use App\Notification\NotificationPreferences;
use App\Preference\Language;
use App\Preference\Theme;
use App\Repository\UserRepository;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use App\ValueObject\NewAccount;
use App\ValueObject\PhoneNumber;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, RecordsDomainEvents, TwoFactorInterface
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

    /** Identifiant du fichier dans ImageStorage $avatarStorage */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $avatar = null;

    /** Graine TOTP (base32), chiffrée en base */
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column]
    private bool $totpEnabled = false;

    /** Dernier pas de temps TOTP accepté : un code déjà utilisé (ou plus ancien) ne sert plus */
    #[ORM\Column(nullable: true)]
    private ?int $totpLastTimestep = null;

    /**
     * Génération des sessions : chaque session note celle de sa connexion, et les sessions d'une
     * génération passée sont fermées (activation de la 2FA, réinitialisation d'accès).
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $sessionGeneration = 0;

    /** Empreinte SHA-256 du jeton d'abonnement calendrier (le jeton n'est jamais stocké) */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $calendarTokenHash = null;

    /** Modération : compte suspendu par le président */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $suspensionReason = null;

    /**
     * Empreintes des codes de secours non utilisés (HMAC, clé du serveur ; SHA-256 seul pour les
     * codes d'avant octobre 2026), calculées par App\Security\TwoFactor\BackupCodes.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $backupCodes = [];

    /** Langue des emails et notifications (et de l'interface à la connexion) */
    #[ORM\Column(length: 2, enumType: Language::class, options: ['default' => 'fr'])]
    private Language $language = Language::French;

    #[ORM\Column(length: 6, enumType: Theme::class, options: ['default' => 'system'])]
    private Theme $theme = Theme::System;

    /**
     * Validé par le président ou l'administrateur : l'espace membres (annuaire, événements, photos,
     * messagerie) n'est ouvert qu'ensuite. Une adresse confirmée ne suffit pas.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    private function __construct(EmailAddress $email, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->email = $email->value;
        $this->createdAt = $now;
    }

    /** Inscription classique : le compte reste inactif jusqu'à la vérification de l'email. */
    public static function register(NewAccount $account, string $hashedPassword, \DateTimeImmutable $now): self
    {
        $user = new self($account->email, $now);
        $user->language = $account->language;
        $user->password = $hashedPassword;
        $user->phone = $account->phone?->e164;
        $user->recordThat(new UserRegistered($user->id));

        return $user;
    }

    /** Inscription via un fournisseur OAuth qui a déjà vérifié l'email. */
    public static function registerViaOAuth(EmailAddress $email, Language $language, \DateTimeImmutable $now): self
    {
        $user = new self($email, $now);
        $user->language = $language;
        $user->verified = true;
        $user->recordThat(new UserRegistered($user->id, viaOAuth: true));
        $user->recordThat(new MemberAwaitingApproval($user->id));

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
    /** ROLE_MEMBER une fois validé ; avant, ROLE_USER seul (son compte, rien d'autre). Le bureau en hérite. */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, $this->isApproved() ? Role::Member->value : 'ROLE_USER']));
    }

    public function isApproved(): bool
    {
        return null !== $this->approvedAt || $this->isBoardMember();
    }

    /** Validé par le bureau ; sans effet sur un compte déjà validé. */
    public function approve(\DateTimeImmutable $now): void
    {
        if (!$this->verified) {
            throw new \LogicException('Le compte doit être vérifié avant d\'être validé.');
        }

        if (null === $this->approvedAt) {
            $this->approvedAt = $now;
            $this->recordThat(new MemberApproved($this->id));
        }
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

    /** Adresse confirmée : le bureau est prévenu qu'un compte attend sa validation (MemberAwaitingApproval). */
    public function markVerified(): void
    {
        if (!$this->verified && !$this->isApproved()) {
            $this->recordThat(new MemberAwaitingApproval($this->id));
        }
        $this->verified = true;
    }

    /**
     * Adresse prouvée par un fournisseur OAuth. Si le compte n'était pas confirmé,
     * son mot de passe a pu être choisi par quelqu'un d'autre que le titulaire de
     * l'adresse : il est supprimé (prise de contrôle préalable impossible).
     */
    public function verifyThroughProvider(): void
    {
        if (!$this->verified) {
            $this->password = '';
        }
        $this->markVerified();
    }

    /** Compte existant relié à un fournisseur OAuth : l'adresse est prouvée, et le titulaire en est averti. */
    public function linkProvider(string $provider): void
    {
        $this->verifyThroughProvider();
        $this->recordThat(new ProviderLinked($this->id, $provider));
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

    public function language(): Language
    {
        return $this->language;
    }

    public function theme(): Theme
    {
        return $this->theme;
    }

    /** Préférences d'affichage choisies dans l'interface ; la langue sert aussi aux emails. */
    public function changeDisplayPreferences(Language $language, Theme $theme): void
    {
        $this->language = $language;
        $this->theme = $theme;
    }

    public function avatar(): ?string
    {
        return $this->avatar;
    }

    /** Nouvel avatar (ou aucun) ; l'ancien (avatar()) reste à supprimer du stockage par l'appelant. */
    public function changeAvatar(?string $avatarId): void
    {
        $this->avatar = $avatarId;
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
     * @param list<string> $backupCodeHashes empreintes des codes de secours (BackupCodes), jamais les codes
     */
    public function enableTotp(array $backupCodeHashes): void
    {
        if (null === $this->totpSecret) {
            throw new \LogicException('Aucune configuration TOTP en cours.');
        }

        $this->totpEnabled = true;
        $this->backupCodes = $backupCodeHashes;
    }

    /** Un code TOTP n'est accepté qu'une fois : son pas de temps doit suivre le dernier utilisé */
    public function acceptsTotpTimestep(int $timestep): bool
    {
        return null === $this->totpLastTimestep || $timestep > $this->totpLastTimestep;
    }

    public function useTotpTimestep(int $timestep): void
    {
        if (!$this->acceptsTotpTimestep($timestep)) {
            throw new \LogicException('Code TOTP déjà utilisé.');
        }

        $this->totpLastTimestep = $timestep;
    }

    /** Toutes les sessions ouvertes jusqu'ici sont fermées à leur prochaine requête. */
    public function revokeSessions(): void
    {
        ++$this->sessionGeneration;
    }

    public function sessionGeneration(): int
    {
        return $this->sessionGeneration;
    }

    /** Session ouverte à cette génération (0 si inconnue) et fermée depuis par revokeSessions() */
    public function isSessionRevoked(?int $generation): bool
    {
        return ($generation ?? 0) < $this->sessionGeneration;
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
        $this->totpEnabled = false;
        $this->backupCodes = [];
    }

    /**
     * Nouveau lien d'abonnement calendrier (jeton tiré par l'appelant, conservé
     * seulement par son empreinte) ; l'ancien cesse de fonctionner.
     */
    public function replaceCalendarToken(string $token): void
    {
        if (\strlen($token) < 32) {
            throw new \LogicException('Jeton de calendrier trop court.');
        }
        $this->calendarTokenHash = hash('sha256', $token);
    }

    public function revokeCalendarToken(): void
    {
        $this->calendarTokenHash = null;
    }

    public function hasCalendarFeed(): bool
    {
        return null !== $this->calendarTokenHash;
    }

    public function suspend(string $reason, \DateTimeImmutable $now): void
    {
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \InvalidArgumentException('Le motif de suspension est obligatoire.');
        }

        $this->suspendedAt = $now;
        $this->suspensionReason = mb_substr($reason, 0, 200);
    }

    public function reactivate(): void
    {
        $this->suspendedAt = null;
        $this->suspensionReason = null;
    }

    public function isSuspended(): bool
    {
        return null !== $this->suspendedAt;
    }

    public function suspensionReason(): ?string
    {
        return $this->suspensionReason;
    }

    public function suspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    /** Peut se connecter, être contacté et notifié. */
    public function isActive(): bool
    {
        return $this->verified && !$this->isSuspended();
    }

    /** @return list<Role> rôles attribués explicitement (hors ROLE_MEMBER implicite) */
    public function assignedRoles(): array
    {
        return array_values(array_filter(array_map(Role::tryFrom(...), $this->roles)));
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

    public function hasBackupCode(string $hash): bool
    {
        return \in_array($hash, $this->backupCodes, true);
    }

    /** Code de secours utilisé : il ne servira plus */
    public function useBackupCode(string $hash): void
    {
        $this->backupCodes = array_values(array_filter($this->backupCodes, static fn (string $h): bool => $h !== $hash));
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
