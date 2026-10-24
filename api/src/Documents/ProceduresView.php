<?php

namespace App\Documents;

use App\ClubEvent\ClubTime;
use App\Entity\ClubEvent;
use App\Entity\EventProcedure;
use App\Repository\EventProcedureRepository;

/**
 * Démarches d'une soirée pour le bureau : la liste (destinataire, date conseillée, avancement) et le
 * document d'une démarche, rendu ici (seule source des textes).
 */
final class ProceduresView
{
    public function __construct(
        private readonly DocumentTemplates $templates,
        private readonly DocumentContexts $contexts,
        private readonly DocumentRenderer $renderer,
        private readonly EventProcedureRepository $procedures,
    ) {
    }

    /** @return array<string, mixed> */
    public function list(ClubEvent $event, bool $mayEdit): array
    {
        $context = $this->contexts->for($event);
        $saved = $this->procedures->forEvent($event);
        $certificate = $context->profile->certificate();

        return [
            'event' => self::event($event),
            'procedures' => array_map(
                fn (DocumentTemplate $template): array => self::summary($saved[$template->type()->value] ?? EventProcedure::start($event, $template), $template, $context),
                $this->templates->all(),
            ),
            'profile' => [
                'missing' => $context->details()->missing(),
                'certificateValidUntil' => $certificate?->validUntil->format('Y-m-d'),
                'certificateCoversEvent' => $certificate?->isValidOn($event->getStartsAt()) ?? false,
            ],
            'mayEdit' => $mayEdit,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(EventProcedure $procedure, bool $mayEdit): array
    {
        $event = $procedure->event();
        $context = $this->contexts->for($event);
        $document = $this->renderer->render($context, $procedure->type(), $procedure->content());

        return [
            'event' => self::event($event),
            ...self::summary($procedure, $this->templates->for($procedure->type()), $context),
            'updatedBy' => $procedure->updatedBy()?->displayName()->value,
            ...$document->toArray(),
            'missing' => $context->details()->missing(),
            'mayEdit' => $mayEdit,
        ];
    }

    /**
     * Document avec des changements pas encore enregistrés (aperçu pendant la saisie).
     *
     * @return array<string, mixed>
     */
    public function preview(EventProcedure $procedure, ProcedureContent $content): array
    {
        return $this->renderer->render($this->contexts->for($procedure->event()), $procedure->type(), $content)->toArray();
    }

    /** @return array<string, mixed> */
    private static function summary(EventProcedure $procedure, DocumentTemplate $template, DocumentContext $context): array
    {
        $days = $template->daysBefore();
        $day = $procedure->event()->getStartsAt()->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE));

        return [
            'type' => $procedure->type()->value,
            'status' => $procedure->status()->value,
            'recipient' => $template->recipient($context),
            'deadline' => null === $days ? null : $day->modify(\sprintf('-%d days', $days))->format('Y-m-d'),
            'sentOn' => $procedure->sentOn()?->format('Y-m-d'),
            'receivedOn' => $procedure->receivedOn()?->format('Y-m-d'),
            'updatedAt' => $procedure->updatedAt()?->format(\DATE_ATOM),
            'edited' => $procedure->isEdited(),
        ];
    }

    /** @return array<string, string> */
    private static function event(ClubEvent $event): array
    {
        return [
            'id' => $event->getId()->toRfc4122(),
            'title' => $event->getTitle(),
            'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
        ];
    }
}
