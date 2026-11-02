<?php

namespace App\EventAccounts;

use App\Entity\EventAccounts;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Bilan d'une soirée en CSV (tableur), dans la langue de la personne qui l'exporte : articles de la
 * buvette, autres lignes, totaux et résultat. Montants décimaux « 30.50 ».
 */
final class AccountsCsvExporter
{
    /** Clés des colonnes (domaine « exports ») */
    private const HEADER = ['section', 'label', 'planned', 'bought', 'returned', 'sold', 'left', 'purchase_price', 'sale_price', 'planned_cost', 'cost', 'planned_income', 'income'];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function export(EventAccounts $accounts, string $locale = 'fr'): string
    {
        $out = fopen('php://temp', 'r+');
        \assert(false !== $out);
        fwrite($out, "\u{FEFF}");
        $label = fn (string $key): string => $this->translator->trans('accounts_csv.'.$key, [], 'exports', $locale);
        self::row($out, array_map($label, self::HEADER));

        foreach ($accounts->items() as $item) {
            $stock = $item->stock();
            $formats = $item->formats();
            self::row($out, [
                $label('bar'), $item->label(),
                (string) $stock->quantities->planned, (string) $stock->quantities->bought, (string) $stock->quantities->returned,
                (string) $formats->soldCount(), (string) ($item->left() ?? ''),
                $stock->containerPrice->toDecimal(), self::salePrices($formats),
                $item->plannedCost()->toDecimal(), $item->cost()->toDecimal(), $item->plannedIncome()->toDecimal(), $item->income()->toDecimal(),
            ]);
        }

        foreach ($accounts->entries() as $entry) {
            $amounts = $entry->amounts();
            $planned = $amounts->planned->toDecimal();
            $actual = $amounts->actual?->toDecimal() ?? '';
            $expense = EntryKind::Expense === $entry->kind();
            self::row($out, [
                $label($expense ? 'expense' : 'income_line'), $entry->label(), '', '', '', '', '', '', '',
                $expense ? $planned : '', $expense ? $actual : '', $expense ? '' : $planned, $expense ? '' : $actual,
            ]);
        }

        self::row($out, [
            $label('total'), '', '', '', '', '', '', '', '',
            $accounts->plannedCosts()->toDecimal(), $accounts->costs()->toDecimal(), $accounts->plannedIncome()->toDecimal(), $accounts->income()->toDecimal(),
        ]);
        self::row($out, [$label('result'), '', '', '', '', '', '', '', '', '', '', $accounts->plannedResult()->toDecimal(), $accounts->result()->toDecimal()]);

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Un seul format : son prix ; plusieurs : « Verre 2.00 / Pichet 10.00 » */
    private static function salePrices(SaleFormats $formats): string
    {
        if (1 === \count($formats->all)) {
            return $formats->first()->price->toDecimal();
        }

        return implode(' / ', array_map(static fn (SaleFormat $format): string => $format->name.' '.$format->price->toDecimal(), $formats->all));
    }

    /**
     * @param resource     $out
     * @param list<string> $cells
     */
    private static function row($out, array $cells): void
    {
        fputcsv($out, array_map(self::cell(...), $cells), ';', escape: '');
    }

    /** Neutralise l'injection de formules dans le tableur (=, +, -, @, tabulation). */
    private static function cell(string $value): string
    {
        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)
            ? "'".$value
            : $value;
    }
}
