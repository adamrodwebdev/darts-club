<?php

declare(strict_types=1);

namespace App\Evening\Income;

use App\Entity\EveningOrder;
use App\Evening\KitchenStatus;

/** Commandes de la soirée, et combien en sont à chaque étape de la cuisine. */
final readonly class OrderTally
{
    /** @param array<string, int> $byStatus commandes par étape de la cuisine */
    private function __construct(
        public int $count,
        private array $byStatus,
    ) {
    }

    /** @param list<EveningOrder> $orders */
    public static function of(array $orders): self
    {
        $byStatus = [];
        foreach ($orders as $order) {
            $status = $order->kitchenStatus();
            if (null !== $status) {
                $byStatus[$status->value] = ($byStatus[$status->value] ?? 0) + 1;
            }
        }

        return new self(\count($orders), $byStatus);
    }

    public function at(KitchenStatus $status): int
    {
        return $this->byStatus[$status->value] ?? 0;
    }
}
