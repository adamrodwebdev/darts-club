<?php

namespace App\Entity;

use App\EventAccounts\BarItemDraft;
use App\EventAccounts\ItemPrices;
use App\EventAccounts\ItemQuantities;
use App\Membership\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Article de la buvette d'une soirée ; créé et modifié par EventAccounts. */
#[ORM\Entity]
#[ORM\Table(name: 'event_bar_item')]
class BarItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventAccounts $accounts;

    #[ORM\Column(length: 80)]
    private string $label;

    #[ORM\Column]
    private int $planned;

    #[ORM\Column]
    private int $bought;

    #[ORM\Column]
    private int $returned;

    #[ORM\Column]
    private int $sold;

    /** Prix unitaires en centimes */
    #[ORM\Column]
    private int $purchasePrice;

    #[ORM\Column]
    private int $salePrice;

    #[ORM\Column]
    private int $position;

    public function __construct(EventAccounts $accounts, BarItemDraft $draft, int $position)
    {
        $this->id = Uuid::v7();
        $this->accounts = $accounts;
        $this->position = $position;
        $this->change($draft);
    }

    public function change(BarItemDraft $draft): void
    {
        $this->label = $draft->label;
        $this->planned = $draft->quantities->planned;
        $this->bought = $draft->quantities->bought;
        $this->returned = $draft->quantities->returned;
        $this->sold = $draft->quantities->sold;
        $this->purchasePrice = $draft->prices->purchase->cents;
        $this->salePrice = $draft->prices->sale->cents;
    }

    public function quantities(): ItemQuantities
    {
        return new ItemQuantities(planned: $this->planned, bought: $this->bought, returned: $this->returned, sold: $this->sold);
    }

    public function prices(): ItemPrices
    {
        return new ItemPrices(Money::cents($this->purchasePrice), Money::cents($this->salePrice));
    }

    /** Payé au fournisseur, une fois le rendu remboursé */
    public function cost(): Money
    {
        return $this->prices()->purchase->times($this->quantities()->kept());
    }

    public function income(): Money
    {
        return $this->prices()->sale->times($this->sold);
    }

    public function plannedCost(): Money
    {
        return $this->prices()->purchase->times($this->planned);
    }

    /** Recette si tout le prévu était vendu */
    public function plannedIncome(): Money
    {
        return $this->prices()->sale->times($this->planned);
    }

    public function label(): string
    {
        return $this->label;
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
