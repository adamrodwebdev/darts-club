<?php

namespace App\Controller;

use App\Dto\TokenSaleInput;
use App\Entity\ClubEvent;
use App\Entity\TokenSale;
use App\Entity\User;
use App\Evening\EntranceDesk;
use App\Evening\EntranceView;
use App\Evening\SaleAlreadyCancelled;
use App\Security\Voter\EveningServiceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans de la soirée en cours. Accueil : vente de jetons et de sachets ; chaque action renvoie
 * l'écran à jour (dernières ventes, argent encaissé), calculé par l'API.
 */
final class EveningServiceController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly EntranceView $entrance,
    ) {
    }

    #[Route('/api/events/{id}/service/entrance', name: 'evening_entrance', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EveningServiceVoter::ENTRANCE, subject: 'event')]
    public function entrance(ClubEvent $event): JsonResponse
    {
        return $this->json($this->entrance->of($event));
    }

    #[Route('/api/events/{id}/service/sales', name: 'evening_sale', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EveningServiceVoter::ENTRANCE, subject: 'event')]
    public function sell(ClubEvent $event, #[MapRequestPayload(acceptFormat: 'json')] TokenSaleInput $input, #[CurrentUser] User $seller, EntranceDesk $desk): JsonResponse
    {
        DomainCall::run(fn () => $desk->sell($seller, $event, $input->toOrder()));

        return $this->json($this->entrance->of($event), Response::HTTP_CREATED);
    }

    #[Route('/api/token-sales/{id}/cancel', name: 'evening_sale_cancel', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EveningServiceVoter::ENTRANCE, subject: 'sale')]
    public function cancel(TokenSale $sale, #[CurrentUser] User $actor, EntranceDesk $desk): JsonResponse
    {
        try {
            $desk->cancel($actor, $sale);
        } catch (SaleAlreadyCancelled $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return $this->json($this->entrance->of($sale->event()));
    }
}
