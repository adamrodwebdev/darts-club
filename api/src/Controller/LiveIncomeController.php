<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClubEvent;
use App\Evening\Income\EveningIncomes;
use App\Evening\Income\IncomeCsvExporter;
use App\Evening\Income\IncomeView;
use App\Security\Voter\EventAccountsVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Recettes en direct d'une soirée, pour tout le bureau (qui lit le bilan) : à l'écran, relues à
 * chaque vente et à chaque commande ; en CSV pour le tableur du trésorier.
 */
final class LiveIncomeController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    #[Route('/api/events/{id}/accounts/income', name: 'accounts_income', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EventAccountsVoter::VIEW, subject: 'event')]
    public function show(ClubEvent $event, IncomeView $view): JsonResponse
    {
        return $this->json($view->of($event));
    }

    #[Route('/api/events/{id}/accounts/income.csv', name: 'accounts_income_export', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(EventAccountsVoter::VIEW, subject: 'event')]
    public function export(ClubEvent $event, Request $request, EveningIncomes $incomes, IncomeCsvExporter $exporter): Response
    {
        return new Response($exporter->export($incomes->for($event), $request->getLocale()), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="recettes-'.$event->getStartsAt()->format('Y-m-d').'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
