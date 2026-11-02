<?php

namespace App\Evening\Income;

use App\Entity\EveningOrder;
use App\Entity\TokenSale;
use App\Evening\KitchenStatus;
use App\Evening\OrderLine;
use App\Evening\PaymentMethod;
use App\EventAccounts\CashSettings;
use App\EventAccounts\SignedAmount;
use App\EventAccounts\Station;
use App\Membership\Money;

/**
 * Recettes d'une soirée, recalculées à chaque lecture depuis les ventes et les commandes en base
 * (aucun compteur qui dérive), en centimes. Accueil : l'argent réellement encaissé, ventes annulées
 * exclues. Bar et cuisine : la valeur des jetons dépensés, au prix figé dans chaque ligne de
 * commande ; la part cuisine d'une commande annulée revient au client (ses boissons sont servies).
 */
final readonly class EveningIncome
{
    /** @var list<TokenSale> */
    private array $sales;

    /** @var list<OrderLine> */
    private array $lines;

    private OrderTally $orders;

    /**
     * @param list<TokenSale>    $sales
     * @param list<EveningOrder> $orders
     */
    public function __construct(array $sales, array $orders, private CashSettings $cash)
    {
        $this->sales = array_values(array_filter($sales, static fn (TokenSale $sale): bool => !$sale->isCancelled()));
        $lines = [];
        foreach ($orders as $order) {
            $cancelled = KitchenStatus::Cancelled === $order->kitchenStatus();
            foreach ($order->lines() as $line) {
                if (!$cancelled || !$line->forKitchen()) {
                    $lines[] = $line;
                }
            }
        }
        $this->lines = $lines;
        $this->orders = OrderTally::of($orders);
    }

    public function taken(PaymentMethod $method): Money
    {
        return array_reduce($this->sales, static fn (Money $sum, TokenSale $sale): Money => $method === $sale->method() ? $sum->add($sale->total()) : $sum, Money::zero());
    }

    /** Argent encaissé à l'accueil, en espèces et par carte */
    public function entrance(): Money
    {
        return array_reduce($this->sales, static fn (Money $sum, TokenSale $sale): Money => $sum->add($sale->total()), Money::zero());
    }

    public function tokensEnabled(): bool
    {
        return $this->cash->tokensEnabled;
    }

    public function cashFloat(): Money
    {
        return $this->cash->cashFloat;
    }

    /** Ce que la caisse de l'accueil doit contenir : le fond de caisse et les espèces reçues */
    public function expectedCash(): Money
    {
        return $this->cash->cashFloat->add($this->taken(PaymentMethod::Cash));
    }

    /** @return list<TokenTally> jetons et sachets vendus, dans l'ordre de leur première vente */
    public function tokens(): array
    {
        $tallies = [];
        foreach ($this->sales as $sale) {
            foreach ($sale->lines() as $line) {
                $known = false;
                foreach ($tallies as $i => $tally) {
                    if ($tally->token->sameAs($line->token)) {
                        $tallies[$i] = $tally->plus($line->count);
                        $known = true;
                    }
                }
                if (!$known) {
                    $tallies[] = new TokenTally($line->token, $line->count);
                }
            }
        }

        return $tallies;
    }

    /** Jetons dépensés au bar ou en cuisine */
    public function spentAt(Station $station): Money
    {
        return array_reduce($this->linesAt($station), static fn (Money $sum, OrderLine $line): Money => $sum->add($line->amount()), Money::zero());
    }

    /** Verres, bouteilles ou plats servis par ce poste (ou en préparation) */
    public function servedAt(Station $station): int
    {
        return array_sum(array_map(static fn (OrderLine $line): int => $line->count, $this->linesAt($station)));
    }

    public function spent(): Money
    {
        return $this->spentAt(Station::Bar)->add($this->spentAt(Station::Kitchen));
    }

    /** Jetons vendus pas encore dépensés ; négatif si des jetons d'une autre soirée ont servi */
    public function circulation(): SignedAmount
    {
        return SignedAmount::difference($this->entrance(), $this->spent());
    }

    /** @return array<string, ItemSales> par identifiant d'article, dans l'ordre de sa première commande */
    public function items(): array
    {
        $items = [];
        foreach ($this->lines as $line) {
            $id = $line->item->itemId;
            $items[$id] = isset($items[$id]) ? $items[$id]->with($line) : ItemSales::of($line);
        }

        return $items;
    }

    public function orders(): OrderTally
    {
        return $this->orders;
    }

    /** @return list<OrderLine> */
    private function linesAt(Station $station): array
    {
        return array_values(array_filter($this->lines, static fn (OrderLine $line): bool => $station === $line->item->station));
    }
}
