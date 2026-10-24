<?php

namespace App\Entity;

use App\Repository\PasskeyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Clé d'accès (passkey) d'un adhérent : seule sa clé publique est connue du site. L'empreinte, le
 * visage ou le code qui la déverrouille restent sur l'appareil.
 */
#[ORM\Entity(repositoryClass: PasskeyRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_passkey_credential', columns: ['credential_id'])]
#[ORM\Index(name: 'idx_passkey_user', columns: ['user_id'])]
class Passkey
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** Identifiant de la clé chez l'authentificateur (base64url) */
    #[ORM\Column(type: Types::TEXT)]
    private string $credentialId;

    /** Clé publique COSE (base64url) */
    #[ORM\Column(type: Types::TEXT)]
    private string $publicKey;

    /** Compteur de signatures : 0 pour une clé synchronisée, croissant sinon (copie détectée) */
    #[ORM\Column]
    private int $counter;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $transports;

    #[ORM\Column(length: 36)]
    private string $aaguid;

    /** Clé synchronisée (iCloud, Google…) : elle suit l'adhérent sur ses autres appareils */
    #[ORM\Column]
    private bool $backedUp;

    #[ORM\Column(length: 60)]
    private string $label;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    private function __construct(
        #[ORM\ManyToOne(inversedBy: 'passkeys')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        CredentialRecord $record,
        string $label,
    ) {
        $this->id = Uuid::v7();
        $this->credentialId = Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId);
        $this->publicKey = Base64UrlSafe::encodeUnpadded($record->credentialPublicKey);
        $this->counter = $record->counter;
        $this->transports = array_values($record->transports);
        $this->aaguid = $record->aaguid->toRfc4122();
        $this->backedUp = true === $record->backupStatus;
        $this->label = mb_substr($label, 0, 60);
    }

    /** Fabrique : clé vérifiée à l'enregistrement (PasskeyCeremonies), rattachée à son adhérent par User::addPasskey() */
    public static function register(User $user, CredentialRecord $record, string $label, \DateTimeImmutable $now): self
    {
        $passkey = new self($user, $record, $label);
        $passkey->createdAt = $now;

        return $passkey;
    }

    /** Données nécessaires à la vérification d'une signature */
    public function record(): CredentialRecord
    {
        return CredentialRecord::create(
            publicKeyCredentialId: Base64UrlSafe::decodeNoPadding($this->credentialId),
            type: 'public-key',
            transports: $this->transports,
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: Uuid::fromString($this->aaguid),
            credentialPublicKey: Base64UrlSafe::decodeNoPadding($this->publicKey),
            userHandle: Base64UrlSafe::decodeNoPadding((string) $this->user->passkeyHandle()),
            counter: $this->counter,
            backupEligible: $this->backedUp ? true : null,
            backupStatus: $this->backedUp,
        );
    }

    /** Signature vérifiée : compteur et état de synchronisation à jour */
    public function recordUse(CredentialRecord $verified, \DateTimeImmutable $now): void
    {
        $this->counter = $verified->counter;
        $this->backedUp = true === $verified->backupStatus;
        $this->lastUsedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function credentialId(): string
    {
        return $this->credentialId;
    }

    /** @return array{id: string, label: string, synced: bool, createdAt: string, lastUsedAt: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'label' => $this->label,
            'synced' => $this->backedUp,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'lastUsedAt' => $this->lastUsedAt?->format(\DATE_ATOM),
        ];
    }
}
