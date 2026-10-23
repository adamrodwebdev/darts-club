<?php

namespace App\Controller;

use App\Dto\PaymentInput;
use App\Entity\Membership;
use App\Entity\Payment;
use App\Entity\User;
use App\Membership\MembershipCsvExporter;
use App\Membership\MembershipReminders;
use App\Membership\MembershipStatus;
use App\Membership\Money;
use App\Membership\PaymentDetails;
use App\Membership\PaymentMethod;
use App\Membership\Season;
use App\Membership\Treasury;
use App\Membership\TreasuryView;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Espace trésorier (président et admin y accèdent par la hiérarchie des rôles). */
#[Route('/api/treasury', name: 'treasury_', format: 'json')]
#[IsGranted('ROLE_TREASURER')]
final class TreasuryController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly Treasury $treasury,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(#[MapQueryParameter] ?string $season = null): JsonResponse
    {
        return $this->json($this->treasury->dashboard($this->season($season)));
    }

    #[Route('/memberships', name: 'memberships', methods: ['GET'])]
    public function memberships(MembershipRepository $repository, #[MapQueryParameter] ?string $season = null, #[MapQueryParameter] ?string $status = null): JsonResponse
    {
        $filter = null !== $status ? MembershipStatus::tryFrom($status) : null;
        $now = $this->clock->now();

        $items = array_values(array_filter(
            $repository->forSeason($this->season($season)),
            static fn (Membership $m): bool => null === $filter || $m->status() === $filter,
        ));

        return $this->json([
            'season' => $this->season($season)->label(),
            'items' => array_map(static fn (Membership $m): array => TreasuryView::membership($m, true, $now), $items),
            'methods' => array_map(static fn (PaymentMethod $m): array => ['id' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
        ]);
    }

    #[Route('/seasons/{season}/open', name: 'open_season', requirements: ['season' => '\d{4}-\d{4}'], methods: ['POST'])]
    public function openSeason(string $season, Request $request): JsonResponse
    {
        $created = $this->treasury->openSeason($this->season($season), self::money($request->toArray()['amountDue'] ?? null));

        return $this->json(['created' => $created], Response::HTTP_CREATED);
    }

    #[Route('/memberships', name: 'open_membership', methods: ['POST'])]
    public function openMembership(Request $request, UserRepository $users): JsonResponse
    {
        $data = $request->toArray();
        $user = \is_string($data['userId'] ?? null) ? $users->find($data['userId']) : null;
        if (null === $user || !$user->isVerified()) {
            throw new UnprocessableEntityHttpException('Adhérent introuvable.');
        }

        try {
            $membership = $this->treasury->openFor($user, $this->season(\is_string($data['season'] ?? null) ? $data['season'] : null), self::money($data['amountDue'] ?? null));
        } catch (\InvalidArgumentException|\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json(TreasuryView::membership($membership, true, $this->clock->now()), Response::HTTP_CREATED);
    }

    #[Route('/memberships/{id}', name: 'change_due', requirements: ['id' => self::UUID], methods: ['PATCH'])]
    public function changeAmountDue(Membership $membership, Request $request): JsonResponse
    {
        $this->treasury->changeAmountDue($membership, self::money($request->toArray()['amountDue'] ?? null));

        return $this->json(TreasuryView::membership($membership, true, $this->clock->now()));
    }

    #[Route('/memberships/{id}/payments', name: 'record_payment', requirements: ['id' => self::UUID], methods: ['POST'])]
    public function recordPayment(Membership $membership, #[MapRequestPayload] PaymentInput $input, #[CurrentUser] User $treasurer): JsonResponse
    {
        try {
            $this->treasury->recordPayment(
                $membership,
                new PaymentDetails(
                    amount: Money::fromDecimal($input->amount),
                    method: PaymentMethod::from($input->method),
                    paidAt: new \DateTimeImmutable($input->paidAt),
                    reference: $input->reference,
                ),
                $treasurer,
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json(TreasuryView::membership($membership, true, $this->clock->now()), Response::HTTP_CREATED);
    }

    #[Route('/payments/{id}/cancel', name: 'cancel_payment', requirements: ['id' => self::UUID], methods: ['POST'])]
    public function cancelPayment(Payment $payment, Request $request): JsonResponse
    {
        $reason = $request->toArray()['reason'] ?? '';

        try {
            $this->treasury->cancelPayment($payment, \is_string($reason) ? $reason : '');
        } catch (\InvalidArgumentException|\LogicException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json(TreasuryView::membership($payment->getMembership(), true, $this->clock->now()));
    }

    #[Route('/reminders', name: 'reminders', methods: ['POST'])]
    public function sendReminders(MembershipReminders $reminders): JsonResponse
    {
        return $this->json(['sent' => $reminders->sendDue()]);
    }

    #[Route('/export', name: 'export', methods: ['GET'], format: 'csv')]
    public function export(MembershipCsvExporter $exporter, #[CurrentUser] User $user, #[MapQueryParameter] ?string $season = null): Response
    {
        $season = $this->season($season);

        return new Response($exporter->export($season, $user->language()->value), headers: [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => \sprintf('attachment; filename="cotisations-%s.csv"', $season->label()),
            'Cache-Control' => 'no-store',
        ]);
    }

    private function season(?string $label): Season
    {
        if (null === $label || '' === $label) {
            return $this->treasury->currentSeason();
        }

        try {
            return Season::fromLabel($label);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }

    private static function money(mixed $value): Money
    {
        try {
            return Money::fromDecimal(\is_string($value) || \is_int($value) ? (string) $value : '');
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
