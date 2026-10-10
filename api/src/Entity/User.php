<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\PhoneNumber;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
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

        return $user;
    }

    /** Inscription via un fournisseur OAuth qui a déjà vérifié l'email. */
    public static function registerViaOAuth(EmailAddress $email): self
    {
        $user = new self($email);
        $user->verified = true;

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
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
