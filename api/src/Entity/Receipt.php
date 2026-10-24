<?php

namespace App\Entity;

use App\EventAccounts\NewReceipt;
use App\EventAccounts\StoredReceipt;
use App\Membership\Money;
use App\Repository\ReceiptRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Justificatif d'une soirée (facture, ticket, avoir) : photo réencodée ou PDF, rangé hors de la
 * racine web et servi au bureau seulement. Créé par EventAccounts.
 */
#[ORM\Entity(repositoryClass: ReceiptRepository::class)]
#[ORM\Table(name: 'event_receipt')]
class Receipt
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'receipts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventAccounts $accounts;

    #[ORM\Column(length: 80)]
    private string $label;

    /** Montant en centimes, s'il est indiqué */
    #[ORM\Column(nullable: true)]
    private ?int $amount;

    /** Avoir : montant à déduire (retour fournisseur remboursé) */
    #[ORM\Column]
    private bool $credit;

    #[ORM\Column(length: 40)]
    private string $fileId;

    #[ORM\Column(length: 30)]
    private string $mediaType;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(EventAccounts $accounts, NewReceipt $receipt, ?User $uploadedBy, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->accounts = $accounts;
        $this->label = $receipt->details->label;
        $this->amount = $receipt->details->amount?->cents;
        $this->credit = $receipt->details->credit;
        $this->fileId = $receipt->file->fileId;
        $this->mediaType = $receipt->file->mediaType;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = $now;
    }

    public function file(): StoredReceipt
    {
        return new StoredReceipt($this->fileId, $this->mediaType);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'label' => $this->label,
            'amount' => null === $this->amount ? null : Money::cents($this->amount)->toDecimal(),
            'credit' => $this->credit,
            'type' => $this->file()->isPdf() ? 'pdf' : 'image',
            'uploadedBy' => $this->uploadedBy?->displayName()->value,
            'uploadedAt' => $this->uploadedAt->format(\DATE_ATOM),
        ];
    }

    public function label(): string
    {
        return $this->label;
    }

    public function accounts(): EventAccounts
    {
        return $this->accounts;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
