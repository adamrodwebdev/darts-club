<?php

namespace App\Documents;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\ClubEvent;
use App\Entity\EventProcedure;
use App\Entity\User;
use App\Repository\EventProcedureRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Tient les démarches des soirées : le document modifié (seuls les changements sont gardés) et
 * l'avancement ; chaque enregistrement s'inscrit au journal.
 */
final class Procedures
{
    public function __construct(
        private readonly DocumentTemplates $templates,
        private readonly DocumentContexts $contexts,
        private readonly DocumentRenderer $renderer,
        private readonly EventProcedureRepository $procedures,
        private readonly AuditTrail $audit,
        private readonly ClockInterface $clock,
    ) {
    }

    public function of(ClubEvent $event, DocumentType $type): EventProcedure
    {
        return $this->procedures->of($event, $this->templates->for($type));
    }

    public function record(User $actor, EventProcedure $procedure, ProcedureContent $content): void
    {
        $event = $procedure->event();
        $document = $this->renderer->render($this->contexts->for($event), $procedure->type(), $content);
        $procedure->record($document->content(), $actor, $this->clock->now());
        $this->audit->record($actor, new AuditEntry('procedure.save', $event->getTitle(), ['document' => $procedure->type()->value]));
        $this->procedures->save($procedure);
    }

    public function changeStatus(User $actor, EventProcedure $procedure, ProcedureStatus $status): void
    {
        $procedure->changeStatus($status, $actor, $this->clock->now());
        $this->audit->record($actor, new AuditEntry('procedure.status', $procedure->event()->getTitle(), [
            'document' => $procedure->type()->value,
            'status' => $status->value,
        ]));
        $this->procedures->save($procedure);
    }
}
