<?php

namespace App\Controller;

use App\Documents\DocumentType;
use App\Documents\Procedures;
use App\Documents\ProceduresView;
use App\Dto\ProcedureInput;
use App\Dto\ProcedureStatusInput;
use App\Entity\ClubEvent;
use App\Entity\EventProcedure;
use App\Entity\User;
use App\Security\Voter\EventDocumentsVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Démarches d'une soirée (bureau) : documents pré-remplis que le secrétariat ajuste, imprime ou
 * envoie, et leur avancement. Les textes sont rendus par l'API, en français (administrations).
 */
final class ProceduresController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly Procedures $procedures,
        private readonly ProceduresView $view,
    ) {
    }

    #[Route('/api/events/{id}/procedures', name: 'procedures_list', requirements: ['id' => self::UUID], methods: ['GET'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::VIEW, subject: 'event')]
    public function list(ClubEvent $event): JsonResponse
    {
        return $this->json($this->view->list($event, $this->isGranted(EventDocumentsVoter::EDIT, $event)));
    }

    #[Route('/api/events/{id}/procedures/{type}', name: 'procedures_show', requirements: ['id' => self::UUID, 'type' => new EnumRequirement(DocumentType::class)], methods: ['GET'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::VIEW, subject: 'event')]
    public function show(ClubEvent $event, DocumentType $type): JsonResponse
    {
        return $this->detail($this->procedures->of($event, $type));
    }

    #[Route('/api/events/{id}/procedures/{type}', name: 'procedures_save', requirements: ['id' => self::UUID, 'type' => new EnumRequirement(DocumentType::class)], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::EDIT, subject: 'event')]
    public function save(ClubEvent $event, DocumentType $type, #[MapRequestPayload(acceptFormat: 'json')] ProcedureInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        $procedure = $this->procedures->of($event, $type);
        $this->procedures->record($actor, $procedure, DomainCall::run($input->toContent(...)));

        return $this->detail($procedure);
    }

    /** Document avec les changements en cours, sans rien enregistrer */
    #[Route('/api/events/{id}/procedures/{type}/preview', name: 'procedures_preview', requirements: ['id' => self::UUID, 'type' => new EnumRequirement(DocumentType::class)], methods: ['POST'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::EDIT, subject: 'event')]
    public function preview(ClubEvent $event, DocumentType $type, #[MapRequestPayload(acceptFormat: 'json')] ProcedureInput $input): JsonResponse
    {
        return $this->json($this->view->preview($this->procedures->of($event, $type), DomainCall::run($input->toContent(...))));
    }

    #[Route('/api/events/{id}/procedures/{type}/status', name: 'procedures_status', requirements: ['id' => self::UUID, 'type' => new EnumRequirement(DocumentType::class)], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::EDIT, subject: 'event')]
    public function changeStatus(ClubEvent $event, DocumentType $type, #[MapRequestPayload(acceptFormat: 'json')] ProcedureStatusInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        $procedure = $this->procedures->of($event, $type);
        $this->procedures->changeStatus($actor, $procedure, $input->toStatus());

        return $this->detail($procedure);
    }

    private function detail(EventProcedure $procedure): JsonResponse
    {
        return $this->json($this->view->detail($procedure, $this->isGranted(EventDocumentsVoter::EDIT, $procedure->event())));
    }
}
