<?php

declare(strict_types=1);

namespace App\EventAccounts;

use App\Entity\EventAccounts;
use App\Export\SpreadsheetCsv;
use App\Membership\Money;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Bilan d'une soirée en CSV (tableur), dans la langue de la personne qui l'exporte : articles de la
 * buvette, autres lignes, totaux et résultat. Montants décimaux « 30.50 ». Une soirée à jetons compte
 * l'argent de l'accueil à la place des ventes de la buvette : une ligne le dit.
 */
final class AccountsCsvExporter
{
    /** Clés des colonnes (domaine « exports ») */
    private const HEADER = ['section', 'label', 'planned', 'bought', 'returned', 'sold', 'left', 'purchase_price', 'sale_price', 'planned_cost', 'cost', 'planned_income', 'income'];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @param ?Money $entrance argent encaissé à l'accueil, null si la soirée s'est passée de jetons */
    public function export(EventAccounts $accounts, ?Money $entrance, string $locale = 'fr'): string
    {
        $label = fn (string $key): string => $this->translator->trans('accounts_csv.'.$key, [], 'exports', $locale);
        $rows = [array_map($label, self::HEADER)];

        foreach ($accounts->items() as $item) {
            $stock = $item->stock();
            $formats = $item->formats();
            $rows[] = [
                $label('bar'), $item->label(),
                (string) $stock->quantities->planned, (string) $stock->quantities->bought, (string) $stock->quantities->returned,
                (string) $formats->soldCount(), (string) ($item->left() ?? ''),
                $stock->containerPrice->toDecimal(), self::salePrices($formats),
                $item->plannedCost()->toDecimal(), $item->cost()->toDecimal(), $item->plannedIncome()->toDecimal(), $item->income()->toDecimal(),
            ];
        }
        if (null !== $entrance) {
            $rows[] = [$label('entrance'), $label('entrance_instead'), '', '', '', '', '', '', '', '', '', '', $entrance->toDecimal()];
        }

        foreach ($accounts->entries() as $entry) {
            $amounts = $entry->amounts();
            $planned = $amounts->planned->toDecimal();
            $actual = $amounts->actual?->toDecimal() ?? '';
            $expense = EntryKind::Expense === $entry->kind();
            $rows[] = [
                $label($expense ? 'expense' : 'income_line'), $entry->label(), '', '', '', '', '', '', '',
                $expense ? $planned : '', $expense ? $actual : '', $expense ? '' : $planned, $expense ? '' : $actual,
            ];
        }

        $rows[] = [
            $label('total'), '', '', '', '', '', '', '', '',
            $accounts->plannedCosts()->toDecimal(), $accounts->costs()->toDecimal(), $accounts->plannedIncome()->toDecimal(), $accounts->income($entrance)->toDecimal(),
        ];
        $rows[] = [$label('result'), '', '', '', '', '', '', '', '', '', '', $accounts->plannedResult()->toDecimal(), $accounts->result($entrance)->toDecimal()];

        return SpreadsheetCsv::of($rows);
    }

    /** Un seul format : son prix ; plusieurs : « Verre 2.00 / Pichet 10.00 » */
    private static function salePrices(SaleFormats $formats): string
    {
        if (1 === \count($formats->all)) {
            return $formats->first()->price->toDecimal();
        }

        return implode(' / ', array_map(static fn (SaleFormat $format): string => $format->name.' '.$format->price->toDecimal(), $formats->all));
    }
}
