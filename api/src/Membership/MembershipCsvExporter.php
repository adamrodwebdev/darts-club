<?php

namespace App\Membership;

use App\Repository\MembershipRepository;

/** Export CSV pour le tableur du trésorier (séparateur « ; », UTF-8 avec BOM pour Excel). */
final class MembershipCsvExporter
{
    private const HEADER = ['Saison', 'Adhérent', 'Email', 'Montant dû', 'Payé', 'Reste', 'Statut', 'Date', 'Moyen', 'Montant', 'Référence', 'Annulé'];

    public function __construct(
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function export(Season $season): string
    {
        $out = fopen('php://temp', 'r+');
        \assert(false !== $out);
        fwrite($out, "\u{FEFF}");
        fputcsv($out, self::HEADER, ';', escape: '');

        foreach ($this->memberships->forSeason($season) as $m) {
            $base = [
                $season->label(),
                $m->holderName(),
                $m->getUser()?->getEmail() ?? '',
                $m->amountDue()->toDecimal(),
                $m->paidAmount()->toDecimal(),
                $m->remaining()->toDecimal(),
                $m->status()->label(),
            ];

            $payments = $m->payments();
            if ([] === $payments) {
                fputcsv($out, array_map(self::cell(...), [...$base, '', '', '', '', '']), ';', escape: '');
            }
            foreach ($payments as $p) {
                fputcsv($out, array_map(self::cell(...), [
                    ...$base,
                    $p->paidAt()->format('d/m/Y'),
                    $p->method()->label(),
                    $p->amount()->toDecimal(),
                    $p->reference() ?? '',
                    $p->isCancelled() ? 'oui : '.$p->cancelReason() : '',
                ]), ';', escape: '');
            }
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Neutralise l'injection de formules dans le tableur (=, +, -, @, tabulation). */
    private static function cell(string $value): string
    {
        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)
            ? "'".$value
            : $value;
    }
}
