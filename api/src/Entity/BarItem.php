<?php

namespace App\Entity;

use App\EventAccounts\BarItemDraft;
use App\EventAccounts\ContainerSpec;
use App\EventAccounts\ItemQuantities;
use App\EventAccounts\ItemServing;
use App\EventAccounts\ItemStock;
use App\EventAccounts\SaleFormats;
use App\EventAccounts\Station;
use App\EventAccounts\StockUnit;
use App\Membership\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Article de la carte d'une soirée (et de la buvette de son bilan) ; créé et modifié par
 * EventAccounts. Acheté en contenants (fûts, packs), vendu en formats (verre, pichet, portion).
 */
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

    #[ORM\Column(length: 10, enumType: Station::class)]
    private Station $station;

    #[ORM\Column(length: 10, enumType: StockUnit::class)]
    private StockUnit $stockUnit;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $garnishes;

    #[ORM\Column]
    private bool $alcoholic;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $containerName;

    /** Contenance d'un contenant, en unités de stock (centilitres, unités, portions) */
    #[ORM\Column]
    private int $containerSize;

    /** Quantités en contenants */
    #[ORM\Column]
    private int $planned;

    #[ORM\Column]
    private int $bought;

    #[ORM\Column]
    private int $returned;

    /** Prix d'un contenant en centimes */
    #[ORM\Column]
    private int $purchasePrice;

    /** @var list<array{name: string, volume: int|null, price: int, sold: int}> */
    #[ORM\Column(type: Types::JSON)]
    private array $formats;

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
        $this->station = $draft->serving->station;
        $this->stockUnit = $draft->serving->unit;
        $this->garnishes = $draft->serving->garnishes;
        $this->alcoholic = $draft->serving->alcoholic;
        $this->containerName = $draft->stock->container->name;
        $this->containerSize = $draft->stock->container->size;
        $this->planned = $draft->stock->quantities->planned;
        $this->bought = $draft->stock->quantities->bought;
        $this->returned = $draft->stock->quantities->returned;
        $this->purchasePrice = $draft->stock->containerPrice->cents;
        $this->formats = $draft->formats->toArray();
    }

    public function serving(): ItemServing
    {
        return new ItemServing($this->station, $this->stockUnit, $this->garnishes, $this->alcoholic);
    }

    public function stock(): ItemStock
    {
        return new ItemStock(
            new ContainerSpec($this->containerName, $this->containerSize),
            new ItemQuantities(planned: $this->planned, bought: $this->bought, returned: $this->returned),
            Money::cents($this->purchasePrice),
        );
    }

    public function formats(): SaleFormats
    {
        return SaleFormats::fromArray($this->formats);
    }

    /** Payé au fournisseur, une fois le rendu remboursé */
    public function cost(): Money
    {
        return Money::cents($this->purchasePrice)->times($this->bought - $this->returned);
    }

    public function income(): Money
    {
        return $this->formats()->income();
    }

    public function plannedCost(): Money
    {
        return Money::cents($this->purchasePrice)->times($this->planned);
    }

    /**
     * Recette si tout le prévu partait dans le premier format (verre pour un fût, portion pour un
     * plat) ; nulle tant que la contenance de ce format est inconnue.
     */
    public function plannedIncome(): Money
    {
        $first = $this->formats()->first();
        if (null === $first->volume) {
            return Money::zero();
        }

        return $first->price->times(intdiv($this->planned * $this->containerSize, $first->volume));
    }

    /** Reste en unités de stock (gardé pour une autre soirée ou offert) ; null si une contenance manque */
    public function left(): ?int
    {
        $served = $this->formats()->served();

        return null === $served ? null : $this->stock()->units() - $served;
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
