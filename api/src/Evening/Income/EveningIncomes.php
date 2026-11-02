<?php

namespace App\Evening\Income;

use App\Entity\ClubEvent;
use App\EventAccounts\CashSettings;
use App\Repository\EveningOrderRepository;
use App\Repository\EventAccountsRepository;
use App\Repository\TokenSaleRepository;

/** Recettes d'une soirée, relues en base à chaque appel : ventes de l'accueil, commandes, caisse. */
final class EveningIncomes
{
    public function __construct(
        private readonly TokenSaleRepository $sales,
        private readonly EveningOrderRepository $orders,
        private readonly EventAccountsRepository $books,
    ) {
    }

    public function for(ClubEvent $event): EveningIncome
    {
        $cash = $this->books->findForEvent($event)?->cash() ?? CashSettings::clubDefaults();

        return new EveningIncome($this->sales->forEvent($event), $this->orders->forEvent($event), $cash);
    }
}
