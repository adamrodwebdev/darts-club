<?php

namespace App\Documents\Template;

use App\Documents\DocumentContext;
use App\Documents\DocumentSheet;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentType;
use App\Documents\EventPhrases;
use App\Documents\FieldDefinition;
use App\Documents\FieldValues;
use App\Documents\FrenchDates;
use App\Documents\LetterParts;

/**
 * Demande d'attestation d'assurance responsabilité civile pour la soirée, à l'assureur de
 * l'association : la mairie et le propriétaire de la salle la demandent.
 */
final class InsuranceTemplate implements DocumentTemplate
{
    public function type(): DocumentType
    {
        return DocumentType::Insurance;
    }

    public function daysBefore(): int
    {
        return 15;
    }

    public function expectedByDefault(): bool
    {
        return true;
    }

    public function recipient(DocumentContext $context): string
    {
        return $context->details()->insurerName ?? 'Assureur de l’association';
    }

    public function fields(DocumentContext $context): array
    {
        $details = $context->details();
        $event = $context->event;

        return [
            FieldDefinition::textarea('insurer', $details->insurerName ?? ''),
            FieldDefinition::text('policy', $details->insurancePolicy ?? ''),
            FieldDefinition::text('when', EventPhrases::when($event)),
            FieldDefinition::text('place', $event->getLocation()),
            FieldDefinition::text('activities', 'buvette et animation musicale'),
            FieldDefinition::text('attendance', EventPhrases::attendance($event)),
            LetterParts::signerField(),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $details = $context->details();
        $insurer = $values->text('insurer');
        $policy = $values->text('policy');
        $attendance = $values->text('attendance');

        return new DocumentSheet([
            LetterParts::sender($details),
            LetterParts::recipient('' === $insurer ? '[nom et adresse de l’assureur]' : $insurer),
            LetterParts::placeAndDate($context->facts->today),
            LetterParts::subject('demande d’attestation d’assurance responsabilité civile'.('' === $policy ? '' : ' · contrat n° '.$policy)),
            LetterParts::paragraph('salutation', 'Madame, Monsieur,'),
            LetterParts::paragraph('event', 'L’association '.$details->officialName.' organise « '.$context->event->getTitle().' », '.$values->text('when').', '.$values->text('place').' : '.$values->text('activities').('' === $attendance ? '' : ', environ '.$attendance.' personnes attendues').'.'),
            LetterParts::paragraph('request', 'La mairie et le propriétaire de la salle nous demandent une attestation d’assurance responsabilité civile couvrant cette manifestation. Merci de nous l’adresser, en précisant les garanties et les montants assurés.'),
            LetterParts::politeness('Madame, Monsieur'),
            LetterParts::signature($details, $values->text('signer')),
            LetterParts::note('certificate', $this->certificateNote($context)),
        ]);
    }

    private function certificateNote(DocumentContext $context): string
    {
        $certificate = $context->profile->certificate();
        if (null === $certificate) {
            return 'Aucune attestation annuelle n’est rangée dans la fiche de l’association : celle de l’assureur peut y être ajoutée.';
        }

        return $certificate->isValidOn($context->event->getStartsAt())
            ? 'L’attestation annuelle rangée dans la fiche (valable jusqu’au '.FrenchDates::date($certificate->validUntil).') couvre déjà cette date : elle peut suffire.'
            : 'L’attestation annuelle rangée dans la fiche a expiré le '.FrenchDates::date($certificate->validUntil).' : il en faut une nouvelle.';
    }
}
