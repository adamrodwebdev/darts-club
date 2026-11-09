<?php

declare(strict_types=1);

namespace App\Entity;

use App\Evening\NewTokenSale;
use App\Evening\PaymentMethod;
use App\Evening\SaleAlreadyCancelled;
use App\Evening\SaleLine;
use App\Membership\Money;
use App\Repository\TokenSaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Vente de jetons et de sachets à l'accueil, en espèces ou par carte. Une annulation la garde
 * (barrée, inscrite au journal) : une vente ne s'efface jamais.
 */
#[ORM\Entity(repositoryClass: TokenSaleRepository::class)]
#[ORM\Table(name: 'token_sale')]
#[ORM\Index(name: 'idx_token_sale_event_sold_at', columns: ['event_id', 'sold_at'])]
class TokenSale
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** @var list<array{token: array{name: string, value: int, bag: bool, color: string|null}, count: int}> */
    #[ORM\Column(type: Types::JSON)]
    private array $lines;

    /** En centimes */
    #[ORM\Column]
    private int $total;

    #[ORM\Column(length: 10, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        NewTokenSale $sale,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $soldBy,
        #[ORM\Column]
        private \DateTimeImmutable $soldAt,
    ) {
        $this->id = $sale->id;
        $this->method = $sale->method;
        $this->lines = array_map(static fn (SaleLine $line): array => $line->toArray(), $sale->lines);
        $this->total = array_reduce($sale->lines, static fn (int $sum, SaleLine $line): int => $sum + $line->amount()->cents, 0);
    }

    public function cancel(User $by, \DateTimeImmutable $now): void
    {
        if ($this->isCancelled()) {
            throw new SaleAlreadyCancelled();
        }
        $this->cancelledAt = $now;
        $this->cancelledBy = $by;
    }

    public function isCancelled(): bool
    {
        return null !== $this->cancelledAt;
    }

    public function total(): Money
    {
        return Money::cents($this->total);
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    /** @return list<SaleLine> */
    public function lines(): array
    {
        return array_map(static fn (array $line): SaleLine => SaleLine::fromArray($line), $this->lines);
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
            'lines' => array_map(static fn (SaleLine $line): array => [
                'name' => $line->token->name,
                'value' => $line->token->value->toDecimal(),
                'bag' => $line->token->bag,
                'color' => $line->token->color?->value,
                'count' => $line->count,
            ], $this->lines()),
            'total' => $this->total()->toDecimal(),
            'method' => $this->method->value,
            'soldAt' => $this->soldAt->format(\DATE_ATOM),
            'soldBy' => $this->soldBy?->displayName()->value,
            'cancelled' => $this->isCancelled(),
            'cancelledAt' => $this->cancelledAt?->format(\DATE_ATOM),
            'cancelledBy' => $this->cancelledBy?->displayName()->value,
        ];
    }
}
