<?php

namespace App\Entity;

use App\Evening\NewOrder;
use App\Evening\OrderLine;
use App\Evening\PlacedOrder;
use App\Evening\Stamp;
use App\EventAccounts\AccountsException;
use App\EventAccounts\AccountsFlags;
use App\EventAccounts\BarItemDraft;
use App\EventAccounts\CashSettings;
use App\EventAccounts\EntryDraft;
use App\EventAccounts\EntryKind;
use App\EventAccounts\ItemAvailability;
use App\EventAccounts\NewReceipt;
use App\EventAccounts\SignedAmount;
use App\EventAccounts\TokenKind;
use App\Membership\Money;
use App\Repository\EventAccountsRepository;
use App\Translation\InvalidValue;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Bilan d'une soirée : articles de la buvette, autres dépenses et recettes, justificatifs. Toute
 * modification passe par lui : une fois clôturé, il ne change plus (le trésorier peut le rouvrir).
 */
#[ORM\Entity(repositoryClass: EventAccountsRepository::class)]
#[ORM\Table(name: 'event_accounts')]
class EventAccounts
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ClubEvent $event;

    #[ORM\Column]
    private bool $temporaryBar = false;

    #[ORM\Column]
    private bool $taxExempt = false;

    /** Consommations payées en jetons achetés à l'accueil */
    #[ORM\Column]
    private bool $tokensEnabled = false;

    /** @var list<array{name: string, value: int, bag: bool}> */
    #[ORM\Column(type: Types::JSON)]
    private array $tokens = [];

    /** Fond de caisse de l'accueil, en centimes */
    #[ORM\Column]
    private int $cashFloat = 0;

    /** Numéro de la dernière commande de la soirée (le bar annonce le suivant) */
    #[ORM\Column]
    private int $lastOrderNumber = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    /** @var Collection<int, BarItem> */
    #[ORM\OneToMany(targetEntity: BarItem::class, mappedBy: 'accounts', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    /** @var Collection<int, AccountsEntry> */
    #[ORM\OneToMany(targetEntity: AccountsEntry::class, mappedBy: 'accounts', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $entries;

    /** @var Collection<int, Receipt> */
    #[ORM\OneToMany(targetEntity: Receipt::class, mappedBy: 'accounts', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['uploadedAt' => 'ASC'])]
    private Collection $receipts;

    private function __construct(ClubEvent $event)
    {
        $this->id = Uuid::v7();
        $this->event = $event;
        $this->items = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->receipts = new ArrayCollection();
    }

    /** Bilan ouvert à la première saisie, avec l'encaissement habituel du club (jetons) */
    public static function open(ClubEvent $event): self
    {
        $accounts = new self($event);
        $accounts->applyCash(CashSettings::clubDefaults());

        return $accounts;
    }

    public function configureCash(CashSettings $settings): void
    {
        $this->assertOpen();
        $this->applyCash($settings);
    }

    public function cash(): CashSettings
    {
        return new CashSettings(
            $this->tokensEnabled,
            array_map(static fn (array $token): TokenKind => TokenKind::fromArray($token), $this->tokens),
            Money::cents($this->cashFloat),
        );
    }

    public function addItem(BarItemDraft $draft): BarItem
    {
        $this->assertOpen();
        $item = new BarItem($this, $draft, $this->items->count() + 1);
        $this->items->add($item);

        return $item;
    }

    /** Pendant la soirée, les ventes comptées par les commandes restent celles du serveur */
    public function changeItem(BarItem $item, BarItemDraft $draft): void
    {
        $this->assertOpen();
        $item->change($this->lastOrderNumber > 0 ? $draft->keepingSoldOf($item->formats()) : $draft);
    }

    /**
     * Prend une commande : numéro suivant de la soirée, lignes figées aux prix de la carte, stock
     * décompté. Chaque ligne est vérifiée avant le premier décompte.
     */
    public function placeOrder(NewOrder $order, Stamp $taken): EveningOrder
    {
        $this->assertOpen();
        $lines = [];
        foreach ($order->lines as $request) {
            $item = $this->item($request->itemId) ?? throw new InvalidValue('Article inconnu pour cette soirée.');
            $lines[] = [$item, new OrderLine($item->offer($request->format, $request->garnishes), $request->count, $request->garnishes)];
        }
        foreach ($lines as [$item, $line]) {
            $item->sell($line->item->format, $line->count);
        }
        ++$this->lastOrderNumber;

        $placed = new PlacedOrder(id: $order->id, number: $this->lastOrderNumber, note: $order->note, lines: array_column($lines, 1));

        return new EveningOrder($this->event, $placed, $taken);
    }

    /** Part cuisine annulée : les plats reviennent au stock */
    public function restock(EveningOrder $order): void
    {
        $this->assertOpen();
        foreach ($order->kitchenLines() as $line) {
            $this->item($line->item->itemId)?->restock($line->item->format, $line->count);
        }
    }

    public function changeItemAvailability(BarItem $item, ItemAvailability $availability): void
    {
        $this->assertOpen();
        $item->changeAvailability($availability);
    }

    /** Numéro que prendra la prochaine commande */
    public function nextOrderNumber(): int
    {
        return $this->lastOrderNumber + 1;
    }

    public function removeItem(BarItem $item): void
    {
        $this->assertOpen();
        $this->items->removeElement($item);
    }

    public function addEntry(EntryDraft $draft): AccountsEntry
    {
        $this->assertOpen();
        $entry = new AccountsEntry($this, $draft, $this->entries->count() + 1);
        $this->entries->add($entry);

        return $entry;
    }

    public function changeEntry(AccountsEntry $entry, EntryDraft $draft): void
    {
        $this->assertOpen();
        $entry->change($draft);
    }

    public function removeEntry(AccountsEntry $entry): void
    {
        $this->assertOpen();
        $this->entries->removeElement($entry);
    }

    public function addReceipt(NewReceipt $receipt, ?User $by, \DateTimeImmutable $now): Receipt
    {
        $this->assertOpen();
        $added = new Receipt($this, $receipt, $by, $now);
        $this->receipts->add($added);

        return $added;
    }

    public function removeReceipt(Receipt $receipt): void
    {
        $this->assertOpen();
        $this->receipts->removeElement($receipt);
    }

    public function changeFlags(AccountsFlags $flags): void
    {
        $this->assertOpen();
        $this->temporaryBar = $flags->temporaryBar;
        $this->taxExempt = $flags->taxExempt;
    }

    public function close(User $by, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        $this->closedAt = $now;
        $this->closedBy = $by;
    }

    public function reopen(): void
    {
        if (!$this->isClosed()) {
            throw new AccountsException('Ce bilan n\'est pas clôturé.');
        }
        $this->closedAt = null;
        $this->closedBy = null;
    }

    public function isClosed(): bool
    {
        return null !== $this->closedAt;
    }

    /** Coûts de la buvette : achats moins le rendu remboursé */
    public function barCosts(): Money
    {
        return array_reduce($this->items(), static fn (Money $sum, BarItem $item): Money => $sum->add($item->cost()), Money::zero());
    }

    public function barIncome(): Money
    {
        return array_reduce($this->items(), static fn (Money $sum, BarItem $item): Money => $sum->add($item->income()), Money::zero());
    }

    public function costs(): Money
    {
        return $this->barCosts()->add($this->entriesTotal(EntryKind::Expense, planned: false));
    }

    public function income(): Money
    {
        return $this->barIncome()->add($this->entriesTotal(EntryKind::Income, planned: false));
    }

    public function plannedCosts(): Money
    {
        $bar = array_reduce($this->items(), static fn (Money $sum, BarItem $item): Money => $sum->add($item->plannedCost()), Money::zero());

        return $bar->add($this->entriesTotal(EntryKind::Expense, planned: true));
    }

    public function plannedIncome(): Money
    {
        $bar = array_reduce($this->items(), static fn (Money $sum, BarItem $item): Money => $sum->add($item->plannedIncome()), Money::zero());

        return $bar->add($this->entriesTotal(EntryKind::Income, planned: true));
    }

    public function result(): SignedAmount
    {
        return SignedAmount::difference($this->income(), $this->costs());
    }

    public function plannedResult(): SignedAmount
    {
        return SignedAmount::difference($this->plannedIncome(), $this->plannedCosts());
    }

    public function flags(): AccountsFlags
    {
        return new AccountsFlags($this->temporaryBar, $this->taxExempt);
    }

    /** @return list<BarItem> */
    public function items(): array
    {
        return array_values($this->items->toArray());
    }

    /** @return list<AccountsEntry> */
    public function entries(): array
    {
        return array_values($this->entries->toArray());
    }

    /** @return list<Receipt> */
    public function receipts(): array
    {
        return array_values($this->receipts->toArray());
    }

    public function event(): ClubEvent
    {
        return $this->event;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedBy(): ?User
    {
        return $this->closedBy;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    private function entriesTotal(EntryKind $kind, bool $planned): Money
    {
        $total = Money::zero();
        foreach ($this->entries() as $entry) {
            if ($entry->kind() === $kind) {
                $total = $total->add($planned ? $entry->amounts()->planned : $entry->amounts()->actualOrZero());
            }
        }

        return $total;
    }

    private function item(string $id): ?BarItem
    {
        foreach ($this->items as $item) {
            if ($item->getId()->toRfc4122() === $id) {
                return $item;
            }
        }

        return null;
    }

    private function applyCash(CashSettings $settings): void
    {
        $this->tokensEnabled = $settings->tokensEnabled;
        $this->tokens = array_map(static fn (TokenKind $token): array => $token->toArray(), $settings->tokens);
        $this->cashFloat = $settings->cashFloat->cents;
    }

    private function assertOpen(): void
    {
        if ($this->isClosed()) {
            throw new AccountsException('Bilan clôturé : le trésorier peut le rouvrir.');
        }
    }
}
