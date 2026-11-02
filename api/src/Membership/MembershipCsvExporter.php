<?php

namespace App\Membership;

use App\Export\SpreadsheetCsv;
use App\Repository\MembershipRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Export CSV pour le tableur du trésorier (séparateur « ; », UTF-8 avec BOM pour Excel). */
final class MembershipCsvExporter
{
    /** Clés des colonnes (domaine « exports ») */
    private const HEADER = ['season', 'member', 'email', 'amount_due', 'paid', 'remaining', 'status', 'date', 'method', 'amount', 'reference', 'cancelled'];

    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @param string $locale langue du trésorier (en-têtes, statuts, moyens de paiement) */
    public function export(Season $season, string $locale = 'fr'): string
    {
        $rows = [array_map(fn (string $key): string => $this->translator->trans('csv.'.$key, [], 'exports', $locale), self::HEADER)];

        foreach ($this->memberships->forSeason($season) as $m) {
            $base = [
                $season->label(),
                $m->holderName(),
                $m->getUser()?->getEmail() ?? '',
                $m->amountDue()->toDecimal(),
                $m->paidAmount()->toDecimal(),
                $m->remaining()->toDecimal(),
                $this->translator->trans('membership_status.'.$m->status()->value, [], 'enums', $locale),
            ];

            $payments = $m->payments();
            if ([] === $payments) {
                $rows[] = [...$base, '', '', '', '', ''];
            }
            foreach ($payments as $p) {
                $rows[] = [
                    ...$base,
                    $p->paidAt()->format('d/m/Y'),
                    $this->translator->trans('payment_method.'.$p->method()->value, [], 'enums', $locale),
                    $p->amount()->toDecimal(),
                    $p->reference() ?? '',
                    $p->isCancelled() ? $this->translator->trans('csv.cancelled_yes', ['%reason%' => (string) $p->cancelReason()], 'exports', $locale) : '',
                ];
            }
        }

        return SpreadsheetCsv::of($rows);
    }
}
