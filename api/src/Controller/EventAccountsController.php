<?php

namespace App\Controller;

use App\Dto\AccountsEntryInput;
use App\Dto\AccountsFlagsInput;
use App\Dto\BarItemInput;
use App\Entity\AccountsEntry;
use App\Entity\BarItem;
use App\Entity\ClubEvent;
use App\Entity\Receipt;
use App\Entity\User;
use App\EventAccounts\AccountsCsvExporter;
use App\EventAccounts\AccountsException;
use App\EventAccounts\AccountsPermissions;
use App\EventAccounts\Bookkeeper;
use App\EventAccounts\EventAccountsView;
use App\EventAccounts\ReceiptArchive;
use App\EventAccounts\ReceiptDesk;
use App\EventAccounts\ReceiptDetails;
use App\EventAccounts\ReceiptUpload;
use App\Membership\Money;
use App\Repository\EventAccountsRepository;
use App\Security\Voter\EventAccountsVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Bilan d'une soirée (bureau) : chaque modification renvoie le bilan à jour, totaux compris, pour
 * que l'écran n'ait jamais à les recalculer.
 */
final class EventAccountsController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly EventAccountsView $view,
    ) {
    }

    #[Route('/api/events/{id}/accounts', name: 'accounts_show', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EventAccountsVoter::VIEW, subject: 'event')]
    public function show(ClubEvent $event): JsonResponse
    {
        return $this->accounts($event);
    }

    #[Route('/api/events/{id}/accounts/items', name: 'accounts_item_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'event')]
    public function addItem(ClubEvent $event, #[MapRequestPayload(acceptFormat: 'json')] BarItemInput $input, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->addItem($actor, $event, DomainCall::run($input->toDraft(...)));

        return $this->accounts($event, Response::HTTP_CREATED);
    }

    #[Route('/api/event-accounts/items/{id}', name: 'accounts_item_edit', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'item')]
    public function changeItem(BarItem $item, #[MapRequestPayload(acceptFormat: 'json')] BarItemInput $input, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->changeItem($actor, $item, DomainCall::run($input->toDraft(...)));

        return $this->accounts($item->accounts()->event());
    }

    #[Route('/api/event-accounts/items/{id}', name: 'accounts_item_remove', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'item')]
    public function removeItem(BarItem $item, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $event = $item->accounts()->event();
        $bookkeeper->removeItem($actor, $item);

        return $this->accounts($event);
    }

    #[Route('/api/events/{id}/accounts/entries', name: 'accounts_entry_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'event')]
    public function addEntry(ClubEvent $event, #[MapRequestPayload(acceptFormat: 'json')] AccountsEntryInput $input, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->addEntry($actor, $event, DomainCall::run($input->toDraft(...)));

        return $this->accounts($event, Response::HTTP_CREATED);
    }

    #[Route('/api/event-accounts/entries/{id}', name: 'accounts_entry_edit', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'entry')]
    public function changeEntry(AccountsEntry $entry, #[MapRequestPayload(acceptFormat: 'json')] AccountsEntryInput $input, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->changeEntry($actor, $entry, DomainCall::run($input->toDraft(...)));

        return $this->accounts($entry->accounts()->event());
    }

    #[Route('/api/event-accounts/entries/{id}', name: 'accounts_entry_remove', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'entry')]
    public function removeEntry(AccountsEntry $entry, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $event = $entry->accounts()->event();
        $bookkeeper->removeEntry($actor, $entry);

        return $this->accounts($event);
    }

    #[Route('/api/events/{id}/accounts/flags', name: 'accounts_flags', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'event')]
    public function changeFlags(ClubEvent $event, #[MapRequestPayload(acceptFormat: 'json')] AccountsFlagsInput $input, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->changeFlags($actor, $event, $input->toFlags());

        return $this->accounts($event);
    }

    /** Formulaire : file (photo ou PDF), label, amount (facultatif, « 12,50 »), credit (« 1 » pour un avoir) */
    #[Route('/api/events/{id}/accounts/receipts', name: 'accounts_receipt_add', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'event')]
    public function addReceipt(ClubEvent $event, Request $request, #[CurrentUser] User $actor, ReceiptDesk $desk): JsonResponse
    {
        $desk->add($actor, $event, DomainCall::run(static fn (): ReceiptUpload => self::receiptUpload($request)));

        return $this->accounts($event, Response::HTTP_CREATED);
    }

    /** Photo affichée dans la page ; PDF téléchargé (jamais ouvert dans le site) */
    #[Route('/api/event-accounts/receipts/{id}', name: 'accounts_receipt_file', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(EventAccountsVoter::VIEW, subject: 'receipt')]
    public function receiptFile(Receipt $receipt, ReceiptArchive $archive): BinaryFileResponse
    {
        $file = $receipt->file();
        $path = $archive->path($file);
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => $file->mediaType]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->setContentDisposition(
            $file->isPdf() ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            'justificatif-'.$receipt->getId()->toRfc4122().($file->isPdf() ? '.pdf' : '.webp'),
        );

        return $response;
    }

    #[Route('/api/event-accounts/receipts/{id}', name: 'accounts_receipt_remove', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'receipt')]
    public function removeReceipt(Receipt $receipt, #[CurrentUser] User $actor, ReceiptDesk $desk): JsonResponse
    {
        $event = $receipt->accounts()->event();
        $desk->remove($actor, $receipt);

        return $this->accounts($event);
    }

    #[Route('/api/events/{id}/accounts/close', name: 'accounts_close', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventAccountsVoter::EDIT, subject: 'event')]
    public function close(ClubEvent $event, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->close($actor, $event);

        return $this->accounts($event);
    }

    #[Route('/api/events/{id}/accounts/reopen', name: 'accounts_reopen', requirements: ['id' => self::UUID], methods: ['POST'], format: 'json')]
    #[IsGranted(EventAccountsVoter::REOPEN, subject: 'event')]
    public function reopen(ClubEvent $event, #[CurrentUser] User $actor, Bookkeeper $bookkeeper): JsonResponse
    {
        $bookkeeper->reopen($actor, $event);

        return $this->accounts($event);
    }

    #[Route('/api/events/{id}/accounts/export.csv', name: 'accounts_export', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(EventAccountsVoter::VIEW, subject: 'event')]
    public function export(ClubEvent $event, Request $request, EventAccountsRepository $books, AccountsCsvExporter $exporter): Response
    {
        $accounts = $books->findForEvent($event) ?? throw new NotFoundHttpException();

        return new Response($exporter->export($accounts, $request->getLocale()), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bilan-'.$event->getStartsAt()->format('Y-m-d').'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function accounts(ClubEvent $event, int $status = Response::HTTP_OK): JsonResponse
    {
        $permissions = new AccountsPermissions(
            edit: $this->isGranted(EventAccountsVoter::EDIT, $event),
            reopen: $this->isGranted(EventAccountsVoter::REOPEN, $event),
        );

        return $this->json($this->view->of($event, $permissions), $status);
    }

    private static function receiptUpload(Request $request): ReceiptUpload
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new AccountsException('Justificatif : une photo (JPEG, PNG ou WebP) ou un PDF.');
        }
        $amount = trim($request->request->getString('amount'));

        return new ReceiptUpload($file, new ReceiptDetails(
            $request->request->getString('label'),
            '' === $amount ? null : Money::fromDecimal($amount),
            $request->request->getBoolean('credit'),
        ));
    }
}
