<?php

namespace App\Evening;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\ClubEvent\ClubTime;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EveningOrder;
use App\Entity\EventAccounts;
use App\Entity\User;
use App\EventAccounts\ItemAvailability;
use App\Repository\EventAccountsRepository;
use App\Translation\InvalidValue;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Bar et cuisine d'une soirée : prise de commande (stock décompté), plats prêts, remise au client,
 * annulation tant que la cuisine attend, articles épuisés. Le bilan de la soirée est verrouillé le
 * temps de chaque changement : deux appareils qui commandent en même temps passent l'un après
 * l'autre, aucun décompte ne se perd. Les autres écrans sont prévenus.
 */
final class OrderDesk
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventAccountsRepository $books,
        private readonly ClockInterface $clock,
        private readonly AuditTrail $audit,
        private readonly EveningBroadcast $broadcast,
    ) {
    }

    /** Une commande déjà reçue (même identifiant, renvoyée après une coupure) n'est pas prise une seconde fois */
    public function place(User $waiter, ClubEvent $event, NewOrder $request): void
    {
        $known = $this->em->find(EveningOrder::class, $request->id);
        if (null !== $known) {
            if ($known->event() !== $event) {
                throw new InvalidValue('Identifiant de commande déjà utilisé.');
            }

            return;
        }

        $order = $this->em->wrapInTransaction(function () use ($waiter, $event, $request): EveningOrder {
            $order = $this->lockedAccounts($event)->placeOrder($request, new Stamp($waiter, $this->clock->now()));
            $this->em->persist($order);

            return $order;
        });
        $this->broadcast->send($event, ['kind' => 'order', 'order' => $order->toArray()]);
    }

    public function markReady(User $cook, EveningOrder $order): void
    {
        $this->em->wrapInTransaction(function () use ($cook, $order): void {
            $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
            $order->markReady(new Stamp($cook, $this->clock->now()));
        });
        $this->broadcast->send($order->event(), ['kind' => 'order', 'order' => $order->toArray()]);
    }

    public function deliver(User $waiter, EveningOrder $order): void
    {
        $this->em->wrapInTransaction(function () use ($waiter, $order): void {
            $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
            $order->deliver(new Stamp($waiter, $this->clock->now()));
        });
        $this->broadcast->send($order->event(), ['kind' => 'order', 'order' => $order->toArray()]);
    }

    /** Les plats reviennent au stock ; l'annulation est inscrite au journal */
    public function cancel(User $actor, EveningOrder $order): void
    {
        $event = $order->event();
        $this->em->wrapInTransaction(function () use ($actor, $order, $event): void {
            $accounts = $this->lockedAccounts($event);
            $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
            $order->cancel(new Stamp($actor, $this->clock->now()));
            $accounts->restock($order);
            $this->audit->record($actor, new AuditEntry('service.order_cancel', $event->getTitle().' · '.ClubTime::formatDay($event->getStartsAt()), [
                'order' => $order->number(),
                'amount' => $order->total()->toDecimal(),
            ]));
        });
        $this->broadcast->send($event, ['kind' => 'order', 'order' => $order->toArray()]);
    }

    public function changeAvailability(BarItem $item, ItemAvailability $availability): void
    {
        $event = $item->accounts()->event();
        $this->em->wrapInTransaction(function () use ($item, $availability, $event): void {
            $this->lockedAccounts($event)->changeItemAvailability($item, $availability);
        });
        $this->broadcast->send($event, ['kind' => 'menu']);
    }

    private function lockedAccounts(ClubEvent $event): EventAccounts
    {
        $accounts = $this->books->findForEvent($event) ?? throw new InvalidValue('La carte de cette soirée est vide.');
        $this->em->refresh($accounts, LockMode::PESSIMISTIC_WRITE);

        return $accounts;
    }
}
