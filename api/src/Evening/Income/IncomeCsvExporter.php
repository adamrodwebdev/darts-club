<?php

declare(strict_types=1);

namespace App\Evening\Income;

use App\Evening\KitchenStatus;
use App\Evening\PaymentMethod;
use App\EventAccounts\Station;
use App\Export\SpreadsheetCsv;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Recettes d'une soirée en CSV (tableur), dans la langue de la personne qui l'exporte : argent de
 * l'accueil par moyen de paiement et par jeton, attendu en caisse, ventes par article et par format,
 * totaux et jetons en circulation. Montants décimaux « 30.50 ».
 */
final class IncomeCsvExporter
{
    /** Clés des colonnes (domaine « exports ») */
    private const HEADER = ['section', 'label', 'count', 'amount'];

    private const STATIONS = ['bar' => Station::Bar, 'kitchen' => Station::Kitchen];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function export(EveningIncome $income, string $locale = 'fr'): string
    {
        $label = fn (string $key): string => $this->translator->trans('income_csv.'.$key, [], 'exports', $locale);
        $entrance = $label('entrance');
        $rows = [array_map($label, self::HEADER)];

        $rows[] = [$entrance, $label('cash'), '', $income->taken(PaymentMethod::Cash)->toDecimal()];
        $rows[] = [$entrance, $label('card'), '', $income->taken(PaymentMethod::Card)->toDecimal()];
        foreach ($income->tokens() as $tally) {
            $rows[] = [$entrance, $tally->token->name, (string) $tally->count, $tally->amount()->toDecimal()];
        }
        $rows[] = [$entrance, $label('cash_float'), '', $income->cashFloat()->toDecimal()];
        $rows[] = [$entrance, $label('expected_cash'), '', $income->expectedCash()->toDecimal()];

        foreach (self::STATIONS as $key => $station) {
            foreach ($income->items() as $sales) {
                if ($station !== $sales->item->station) {
                    continue;
                }
                foreach ($sales->formats as $format) {
                    $rows[] = [$label($key), $sales->item->label.' · '.$format->format, (string) $format->count, $format->amount->toDecimal()];
                }
            }
        }

        $total = $label('total');
        $orders = $income->orders();
        $rows[] = [$total, $label('entrance_total'), '', $income->entrance()->toDecimal()];
        $rows[] = [$total, $label('bar_total'), (string) $income->servedAt(Station::Bar), $income->spentAt(Station::Bar)->toDecimal()];
        $rows[] = [$total, $label('kitchen_total'), (string) $income->servedAt(Station::Kitchen), $income->spentAt(Station::Kitchen)->toDecimal()];
        $rows[] = [$total, $label('circulation'), '', $income->circulation()->toDecimal()];
        $rows[] = [$total, $label('orders'), (string) $orders->count, ''];
        $rows[] = [$total, $label('cancelled'), (string) $orders->at(KitchenStatus::Cancelled), ''];

        return SpreadsheetCsv::of($rows);
    }
}
