<?php

namespace App\Controller;

use App\Dto\AvailabilityInput;
use App\Dto\OrderInput;
use App\Dto\TokenSaleInput;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\EveningOrder;
use App\Entity\TokenSale;
use App\Entity\User;
use App\Evening\EntranceDesk;
use App\Evening\EntranceView;
use App\Evening\OrderDesk;
use App\Evening\OrderStepRefused;
use App\Evening\SaleAlreadyCancelled;
use App\Evening\ServiceView;
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
 * Écrans de la soirée en cours. Accueil : vente de jetons et de sachets ; bar : prise de commande,
 * remise au client ; cuisine : plats prêts, articles épuisés. Chaque action renvoie l'écran à jour,
 * calculé par l'API ; une étape déjà franchie par un autre appareil répond 409.
 */
final class EveningServiceController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly EntranceView $entrance,
        private readonly ServiceView $service,
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

    #[Route('/api/events/{id}/service/bar', name: 'evening_bar', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EveningServiceVoter::BAR, subject: 'event')]
    public function bar(ClubEvent $event): JsonResponse
    {
        return $this->json($this->service->bar($event));
    }

    #[Route('/api/events/{id}/service/kitchen', name: 'evening_kitchen', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EveningServiceVoter::KITCHEN, subject: 'event')]
    public function kitchen(ClubEvent $event): JsonResponse
    {
        return $this->json($this->service->kitchen($event));
    }

    #[Route('/api/events/{id}/service/orders', name: 'evening_order', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EveningServiceVoter::BAR, subject: 'event')]
    public function order(ClubEvent $event, #[MapRequestPayload(acceptFormat: 'json')] OrderInput $input, #[CurrentUser] User $waiter, OrderDesk $desk): JsonResponse
    {
        DomainCall::run(fn () => $desk->place($waiter, $event, $input->toOrder()));

        return $this->json($this->service->bar($event), Response::HTTP_CREATED);
    }

    #[Route('/api/evening-orders/{id}/ready', name: 'evening_order_ready', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EveningServiceVoter::KITCHEN, subject: 'order')]
    public function ready(EveningOrder $order, #[CurrentUser] User $cook, OrderDesk $desk): JsonResponse
    {
        self::step(fn () => $desk->markReady($cook, $order));

        return $this->json($this->service->kitchen($order->event()));
    }

    #[Route('/api/evening-orders/{id}/deliver', name: 'evening_order_deliver', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EveningServiceVoter::BAR, subject: 'order')]
    public function deliver(EveningOrder $order, #[CurrentUser] User $waiter, OrderDesk $desk): JsonResponse
    {
        self::step(fn () => $desk->deliver($waiter, $order));

        return $this->json($this->service->bar($order->event()));
    }

    #[Route('/api/evening-orders/{id}/cancel', name: 'evening_order_cancel', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EveningServiceVoter::SERVE, subject: 'order')]
    public function cancelOrder(EveningOrder $order, #[CurrentUser] User $actor, OrderDesk $desk): JsonResponse
    {
        self::step(fn () => $desk->cancel($actor, $order));

        return $this->screenFor($order->event());
    }

    #[Route('/api/event-accounts/items/{id}/availability', name: 'evening_item_availability', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(EveningServiceVoter::SERVE, subject: 'item')]
    public function availability(BarItem $item, #[MapRequestPayload(acceptFormat: 'json')] AvailabilityInput $input, OrderDesk $desk): JsonResponse
    {
        DomainCall::run(fn () => $desk->changeAvailability($item, $input->toAvailability()));

        return $this->screenFor($item->accounts()->event(), kitchenFirst: true);
    }

    /** Écran de celui qui agit : la cuisine pour l'équipe de la cuisine, le bar sinon */
    private function screenFor(ClubEvent $event, bool $kitchenFirst = false): JsonResponse
    {
        $kitchen = $this->isGranted(EveningServiceVoter::KITCHEN, $event) && ($kitchenFirst || !$this->isGranted(EveningServiceVoter::BAR, $event));

        return $this->json($kitchen ? $this->service->kitchen($event) : $this->service->bar($event));
    }

    /** @param callable(): void $action */
    private static function step(callable $action): void
    {
        try {
            DomainCall::run($action);
        } catch (OrderStepRefused $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }
    }
}
