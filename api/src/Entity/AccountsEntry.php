<?php

namespace App\Entity;

use App\EventAccounts\EntryAmounts;
use App\EventAccounts\EntryDraft;
use App\EventAccounts\EntryKind;
use App\Membership\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Dépense ou recette d'une soirée hors buvette ; créée et modifiée par EventAccounts. */
#[ORM\Entity]
#[ORM\Table(name: 'event_accounts_entry')]
class AccountsEntry
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventAccounts $accounts;

    #[ORM\Column(length: 10, enumType: EntryKind::class)]
    private EntryKind $kind;

    #[ORM\Column(length: 80)]
    private string $label;

    /** Montants en centimes ; réel inconnu tant que la soirée n'a pas eu lieu */
    #[ORM\Column]
    private int $planned;

    #[ORM\Column(nullable: true)]
    private ?int $actual;

    #[ORM\Column]
    private int $position;

    public function __construct(EventAccounts $accounts, EntryDraft $draft, int $position)
    {
        $this->id = Uuid::v7();
        $this->accounts = $accounts;
        $this->position = $position;
        $this->change($draft);
    }

    public function change(EntryDraft $draft): void
    {
        $this->kind = $draft->kind;
        $this->label = $draft->label;
        $this->planned = $draft->amounts->planned->cents;
        $this->actual = $draft->amounts->actual?->cents;
    }

    public function kind(): EntryKind
    {
        return $this->kind;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function amounts(): EntryAmounts
    {
        return new EntryAmounts(Money::cents($this->planned), null === $this->actual ? null : Money::cents($this->actual));
    }

    /** Ordre de saisie, gardé à l'affichage */
    public function position(): int
    {
        return $this->position;
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
