<?php

namespace App\Entity;

use App\Evening\KitchenStatus;
use App\Evening\OrderLine;
use App\Evening\OrderStepRefused;
use App\Evening\PlacedOrder;
use App\Evening\Stamp;
use App\Membership\Money;
use App\Repository\EveningOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Commande prise au bar : les boissons sont servies sur place, les plats partent en cuisine « en
 * attente ». Chaque étape ne se valide qu'une fois (prête, puis remise au client, qui verrouille la
 * commande ; ou annulée tant qu'elle attend) : un second appareil lit « déjà confirmée ».
 */
#[ORM\Entity(repositoryClass: EveningOrderRepository::class)]
#[ORM\Table(name: 'evening_order')]
#[ORM\UniqueConstraint(name: 'uniq_evening_order_number', columns: ['event_id', 'number'])]
#[ORM\Index(name: 'idx_evening_order_taken_at', columns: ['event_id', 'taken_at'])]
class EveningOrder
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private int $number;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $note;

    /** @var list<array{itemId: string, label: string, station: string, format: string, volume: int|null, price: int, count: int, garnishes: list<string>}> */
    #[ORM\Column(type: Types::JSON)]
    private array $lines;

    /** En centimes */
    #[ORM\Column]
    private int $total;

    /** Null pour des boissons seules : rien ne part en cuisine */
    #[ORM\Column(length: 12, nullable: true, enumType: KitchenStatus::class)]
    private ?KitchenStatus $kitchenStatus;

    #[ORM\Column]
    private \DateTimeImmutable $takenAt;

    /** Compte supprimé : la commande reste, sans son auteur */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $takenBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readyAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $deliveredBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        PlacedOrder $order,
        Stamp $taken,
    ) {
        $this->id = $order->id;
        $this->number = $order->number;
        $this->note = $order->note;
        $this->lines = array_map(static fn (OrderLine $line): array => $line->toArray(), $order->lines);
        $this->total = array_reduce($order->lines, static fn (int $sum, OrderLine $line): int => $sum + $line->amount()->cents, 0);
        $forKitchen = array_filter($order->lines, static fn (OrderLine $line): bool => $line->forKitchen());
        $this->kitchenStatus = [] === $forKitchen ? null : KitchenStatus::Waiting;
        $this->takenAt = $taken->at;
        $this->takenBy = $taken->by;
    }

    /** La cuisine a préparé les plats : le bar est prévenu */
    public function markReady(Stamp $stamp): void
    {
        $this->step(KitchenStatus::Waiting);
        $this->kitchenStatus = KitchenStatus::Ready;
        $this->readyAt = $stamp->at;
    }

    /** Remise au client : la commande se verrouille, elle ne peut plus être servie une seconde fois */
    public function deliver(Stamp $stamp): void
    {
        $this->step(KitchenStatus::Ready);
        $this->kitchenStatus = KitchenStatus::Delivered;
        $this->deliveredAt = $stamp->at;
        $this->deliveredBy = $stamp->by;
    }

    /** Annulée tant que la cuisine ne l'a pas préparée (plat épuisé, client parti) */
    public function cancel(Stamp $stamp): void
    {
        $this->step(KitchenStatus::Waiting);
        $this->kitchenStatus = KitchenStatus::Cancelled;
        $this->cancelledAt = $stamp->at;
    }

    public function kitchenStatus(): ?KitchenStatus
    {
        return $this->kitchenStatus;
    }

    /** @return list<OrderLine> */
    public function lines(): array
    {
        return array_map(static fn (array $line): OrderLine => OrderLine::fromArray($line), $this->lines);
    }

    /** @return list<OrderLine> */
    public function kitchenLines(): array
    {
        return array_values(array_filter($this->lines(), static fn (OrderLine $line): bool => $line->forKitchen()));
    }

    public function total(): Money
    {
        return Money::cents($this->total);
    }

    public function number(): int
    {
        return $this->number;
    }

    public function event(): ClubEvent
    {
        return $this->event;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'number' => $this->number,
            'note' => $this->note,
            'lines' => array_map(static fn (OrderLine $line): array => [
                'itemId' => $line->item->itemId,
                'label' => $line->item->label,
                'station' => $line->item->station->value,
                'format' => $line->item->format,
                'price' => $line->item->price->toDecimal(),
                'count' => $line->count,
                'garnishes' => $line->garnishes,
            ], $this->lines()),
            'total' => $this->total()->toDecimal(),
            'kitchen' => $this->kitchenStatus?->value,
            'takenAt' => $this->takenAt->format(\DATE_ATOM),
            'takenBy' => $this->takenBy?->displayName()->value,
            'readyAt' => $this->readyAt?->format(\DATE_ATOM),
            'deliveredAt' => $this->deliveredAt?->format(\DATE_ATOM),
            'deliveredBy' => $this->deliveredBy?->displayName()->value,
            'cancelledAt' => $this->cancelledAt?->format(\DATE_ATOM),
        ];
    }

    private function step(KitchenStatus $expected): void
    {
        if ($this->kitchenStatus !== $expected) {
            throw OrderStepRefused::because($this->kitchenStatus);
        }
    }
}
