<?php

namespace App\EventAccounts;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\ClubEvent\ClubTime;
use App\Entity\AccountsEntry;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EventAccounts;
use App\Entity\Receipt;
use App\Entity\User;
use App\Repository\EventAccountsRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Tient le bilan des soirées : chaque saisie passe par le bilan (qui refuse tout changement une fois
 * clôturé) et s'inscrit au journal, avec son auteur.
 */
final class Bookkeeper
{
    public function __construct(
        private readonly EventAccountsRepository $books,
        private readonly AuditTrail $audit,
        private readonly ClockInterface $clock,
    ) {
    }

    public function addItem(User $actor, ClubEvent $event, BarItemDraft $draft): void
    {
        $accounts = $this->accountsOf($event);
        $accounts->addItem($draft);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.item_add', ['item' => $draft->label]));
    }

    public function changeItem(User $actor, BarItem $item, BarItemDraft $draft): void
    {
        $accounts = $item->accounts();
        $accounts->changeItem($item, $draft);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.item_edit', ['item' => $draft->label]));
    }

    public function removeItem(User $actor, BarItem $item): void
    {
        $accounts = $item->accounts();
        $accounts->removeItem($item);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.item_remove', ['item' => $item->label()]));
    }

    public function addEntry(User $actor, ClubEvent $event, EntryDraft $draft): void
    {
        $accounts = $this->accountsOf($event);
        $accounts->addEntry($draft);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.entry_add', ['entry' => $draft->label]));
    }

    public function changeEntry(User $actor, AccountsEntry $entry, EntryDraft $draft): void
    {
        $accounts = $entry->accounts();
        $accounts->changeEntry($entry, $draft);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.entry_edit', ['entry' => $draft->label]));
    }

    public function removeEntry(User $actor, AccountsEntry $entry): void
    {
        $accounts = $entry->accounts();
        $accounts->removeEntry($entry);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.entry_remove', ['entry' => $entry->label()]));
    }

    public function changeFlags(User $actor, ClubEvent $event, AccountsFlags $flags): void
    {
        $accounts = $this->accountsOf($event);
        $accounts->changeFlags($flags);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.flags', [
            'temporary_bar' => $flags->temporaryBar,
            'tax_exempt' => $flags->taxExempt,
        ]));
    }

    public function addReceipt(User $actor, ClubEvent $event, NewReceipt $receipt): void
    {
        $accounts = $this->accountsOf($event);
        $accounts->addReceipt($receipt, $actor, $this->clock->now());
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.receipt_add', ['receipt' => $receipt->details->label]));
    }

    public function removeReceipt(User $actor, Receipt $receipt): void
    {
        $accounts = $receipt->accounts();
        $accounts->removeReceipt($receipt);
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.receipt_remove', ['receipt' => $receipt->label()]));
    }

    public function close(User $actor, ClubEvent $event): void
    {
        $accounts = $this->accountsOf($event);
        $accounts->close($actor, $this->clock->now());
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.close'));
    }

    public function reopen(User $actor, ClubEvent $event): void
    {
        $accounts = $this->accountsOf($event);
        $accounts->reopen();
        $this->save($actor, $accounts, self::entry($accounts, 'accounts.reopen'));
    }

    /** Bilan de la soirée, ouvert à la première saisie */
    private function accountsOf(ClubEvent $event): EventAccounts
    {
        return $this->books->findForEvent($event) ?? EventAccounts::open($event);
    }

    /** @param array<string, scalar|null> $details */
    private static function entry(EventAccounts $accounts, string $action, array $details = []): AuditEntry
    {
        $event = $accounts->event();

        return new AuditEntry($action, $event->getTitle().' · '.ClubTime::formatDay($event->getStartsAt()), $details);
    }

    private function save(User $actor, EventAccounts $accounts, AuditEntry $entry): void
    {
        $this->audit->record($actor, $entry);
        $this->books->save($accounts);
    }
}
