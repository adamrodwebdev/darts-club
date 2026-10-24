<?php

namespace App\Documents\Template;

use App\Documents\DocumentContext;
use App\Documents\DocumentSheet;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentType;
use App\Documents\EventPhrases;
use App\Documents\FieldDefinition;
use App\Documents\FieldValues;
use App\Documents\LetterParts;

/**
 * Demande de mise à disposition d'une salle (salle des fêtes, local) : la convention elle-même est
 * rédigée par la commune ou le propriétaire, l'association la demande.
 */
final class VenueTemplate implements DocumentTemplate
{
    public function type(): DocumentType
    {
        return DocumentType::Venue;
    }

    public function daysBefore(): int
    {
        return 30;
    }

    public function expectedByDefault(): bool
    {
        return true;
    }

    public function recipient(DocumentContext $context): string
    {
        return 'Mairie de '.EventPhrases::commune($context->event);
    }

    public function fields(DocumentContext $context): array
    {
        $event = $context->event;

        return [
            FieldDefinition::textarea('owner', "Madame, Monsieur le Maire\nMairie de ".EventPhrases::commune($event)),
            FieldDefinition::text('venue', $event->getLocation()),
            FieldDefinition::text('when', EventPhrases::when($event).', installation et rangement compris'),
            FieldDefinition::text('purpose', $event->getTitle()),
            FieldDefinition::text('attendance', EventPhrases::attendance($event)),
            LetterParts::signerField(),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $details = $context->details();
        $attendance = $values->text('attendance');

        $blocks = [
            LetterParts::sender($details),
            LetterParts::recipient($values->text('owner')),
            LetterParts::placeAndDate($context->facts->today),
            LetterParts::subject('demande de mise à disposition d’une salle'),
            LetterParts::paragraph('salutation', 'Madame, Monsieur,'),
            LetterParts::paragraph('request', 'L’association '.$details->officialName.' sollicite la mise à disposition de '.$values->text('venue').' '.$values->text('when').', pour « '.$values->text('purpose').' ».'),
        ];
        if ('' !== $attendance) {
            $blocks[] = LetterParts::paragraph('attendance', 'Nous attendons environ '.$attendance.' personnes.');
        }

        return new DocumentSheet([
            ...$blocks,
            LetterParts::paragraph('commitments', 'Nous fournirons l’attestation d’assurance responsabilité civile de l’association et nous engageons à respecter le règlement de la salle et à la restituer propre, dans l’état où nous l’aurons trouvée.'),
            LetterParts::paragraph('conditions', 'Merci de nous indiquer les conditions de cette mise à disposition : convention, tarif éventuel, caution et état des lieux.'),
            LetterParts::politeness('Madame, Monsieur'),
            LetterParts::signature($details, $values->text('signer')),
            LetterParts::note('erp', 'Salle utilisée pour une activité différente de son usage habituel (un concert dans un gymnase…) : demande conjointe avec la commune au moins 2 mois avant (sécurité des établissements recevant du public, article GN 6).'),
        ]);
    }
}
