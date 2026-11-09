<?php

declare(strict_types=1);

namespace App\Evening;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\ClubEvent\ClubTime;
use App\Entity\ClubEvent;
use App\Entity\TokenSale;
use App\Entity\User;
use App\EventAccounts\CashSettings;
use App\Repository\EventAccountsRepository;
use App\Repository\TokenSaleRepository;
use App\Translation\InvalidValue;
use Symfony\Component\Clock\ClockInterface;

/**
 * Accueil d'une soirée : vend les jetons et les sachets aux valeurs réglées par le bureau, annule
 * une vente (gardée, barrée, inscrite au journal), prévient les autres écrans.
 */
final class EntranceDesk
{
    public function __construct(
        private readonly TokenSaleRepository $sales,
        private readonly EventAccountsRepository $books,
        private readonly ClockInterface $clock,
        private readonly AuditTrail $audit,
        private readonly EveningBroadcast $broadcast,
    ) {
    }

    /**
     * Les valeurs viennent des réglages de la soirée, jamais de l'appareil. Une vente déjà reçue
     * (même identifiant, renvoyée après une coupure) n'est pas comptée une seconde fois.
     */
    public function sell(User $seller, ClubEvent $event, SaleOrder $order): void
    {
        $known = $this->sales->find($order->id);
        if (null !== $known) {
            if ($known->event() !== $event) {
                throw new InvalidValue('Identifiant de vente déjà utilisé.');
            }

            return;
        }

        $cash = $this->books->findForEvent($event)?->cash() ?? CashSettings::clubDefaults();
        if (!$cash->tokensEnabled) {
            throw new InvalidValue('Les jetons ne sont pas vendus pour cette soirée.');
        }
        $lines = [];
        foreach ($order->counts as $name => $count) {
            $token = $cash->token($name) ?? throw new InvalidValue('Jeton inconnu : « %name% ».', ['%name%' => $name]);
            $lines[] = new SaleLine($token, $count);
        }

        $sale = new TokenSale($event, new NewTokenSale($order->id, $order->method, $lines), soldBy: $seller, soldAt: $this->clock->now());
        $this->sales->save($sale);
        $this->broadcast->send($event, ['kind' => 'sale', 'sale' => $sale->toArray()]);
    }

    public function cancel(User $actor, TokenSale $sale): void
    {
        $sale->cancel($actor, $this->clock->now());
        $event = $sale->event();
        $this->audit->record($actor, new AuditEntry('entrance.sale_cancel', $event->getTitle().' · '.ClubTime::formatDay($event->getStartsAt()), [
            'amount' => $sale->total()->toDecimal(),
            'method' => $sale->method()->value,
        ]));
        $this->sales->save($sale);
        $this->broadcast->send($event, ['kind' => 'sale', 'sale' => $sale->toArray()]);
    }
}
