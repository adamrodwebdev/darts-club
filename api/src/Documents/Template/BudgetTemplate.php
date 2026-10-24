<?php

namespace App\Documents\Template;

use App\Documents\BlockKind;
use App\Documents\DocumentBlock;
use App\Documents\DocumentContext;
use App\Documents\DocumentSheet;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentType;
use App\Documents\EventPhrases;
use App\Documents\FieldValues;
use App\Documents\LetterParts;
use App\Documents\OfficialLink;
use App\Entity\AccountsEntry;
use App\Entity\EventAccounts;
use App\EventAccounts\EntryKind;
use App\Membership\Money;

/**
 * Budget de la soirée, prévu et réel, tiré de son bilan : à joindre à un dossier (mairie, demande de
 * subvention, assemblée générale). Les lignes gardent l'identifiant de leur ligne du bilan.
 */
final class BudgetTemplate implements DocumentTemplate
{
    public function type(): DocumentType
    {
        return DocumentType::Budget;
    }

    public function daysBefore(): ?int
    {
        return null;
    }

    public function expectedByDefault(): bool
    {
        return true;
    }

    public function recipient(DocumentContext $context): string
    {
        return 'Dossier de l’association';
    }

    public function fields(DocumentContext $context): array
    {
        return [];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $event = $context->event;
        $accounts = $context->accounts();
        $intro = [
            new DocumentBlock('heading', BlockKind::Heading, 'Budget de « '.$event->getTitle().' »'),
            self::line('when', 'Date', EventPhrases::when($event)),
            self::line('organizer', 'Organisateur', $context->details()->officialName),
        ];
        if (null === $accounts || ([] === $accounts->items() && [] === $accounts->entries())) {
            return new DocumentSheet([...$intro, LetterParts::note('empty', 'Le bilan de la soirée est vide : ce budget se remplit avec lui.')]);
        }

        return new DocumentSheet([
            ...$intro,
            new DocumentBlock('costs-heading', BlockKind::Heading, 'Dépenses'),
            ...$this->costLines($accounts),
            self::line('costs', 'Total des dépenses', self::amounts($accounts->plannedCosts(), $accounts->costs())),
            new DocumentBlock('income-heading', BlockKind::Heading, 'Recettes'),
            ...$this->incomeLines($accounts),
            self::line('income', 'Total des recettes', self::amounts($accounts->plannedIncome(), $accounts->income())),
            self::line('result', 'Résultat', 'prévu '.EventPhrases::signed($accounts->plannedResult()).' · réel '.EventPhrases::signed($accounts->result())),
            LetterParts::note('subsidy', 'Pour une demande de subvention, le formulaire officiel est le Cerfa 12156*06 (budget du projet).'),
        ], [new OfficialLink('Demande de subvention (Cerfa 12156*06)', 'https://www.service-public.gouv.fr/associations/vosdroits/R1271')]);
    }

    /** @return list<DocumentBlock> */
    private function costLines(EventAccounts $accounts): array
    {
        $lines = [];
        foreach ($accounts->items() as $item) {
            $lines[] = self::line('bar-cost-'.$item->getId(), 'Achats buvette · '.$item->label(), self::amounts($item->plannedCost(), $item->cost()));
        }

        return [...$lines, ...$this->entryLines($accounts, EntryKind::Expense)];
    }

    /** @return list<DocumentBlock> */
    private function incomeLines(EventAccounts $accounts): array
    {
        $lines = [];
        foreach ($accounts->items() as $item) {
            $lines[] = self::line('bar-income-'.$item->getId(), 'Ventes buvette · '.$item->label(), self::amounts($item->plannedIncome(), $item->income()));
        }

        return [...$lines, ...$this->entryLines($accounts, EntryKind::Income)];
    }

    /** @return list<DocumentBlock> */
    private function entryLines(EventAccounts $accounts, EntryKind $kind): array
    {
        $entries = array_filter($accounts->entries(), static fn (AccountsEntry $entry): bool => $kind === $entry->kind());

        return array_values(array_map(
            static fn (AccountsEntry $entry): DocumentBlock => self::line('entry-'.$entry->getId(), $entry->label(), self::amounts($entry->amounts()->planned, $entry->amounts()->actual)),
            $entries,
        ));
    }

    private static function line(string $id, string $label, string $text): DocumentBlock
    {
        return new DocumentBlock($id, BlockKind::Item, $text, $label);
    }

    /** « prévu 120,00 € · réel 110,00 € » ; le réel reste à venir tant qu'il n'est pas saisi */
    private static function amounts(Money $planned, ?Money $actual): string
    {
        return 'prévu '.EventPhrases::money($planned).' · réel '.(null === $actual ? 'à venir' : EventPhrases::money($actual));
    }
}
