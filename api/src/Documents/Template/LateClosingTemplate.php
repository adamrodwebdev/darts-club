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
 * Demande de dérogation exceptionnelle à l'heure de fermeture des débits de boissons, fixée par
 * arrêté préfectoral : l'autorité compétente (sous-préfecture ou mairie) est à confirmer.
 */
final class LateClosingTemplate implements DocumentTemplate
{
    public function type(): DocumentType
    {
        return DocumentType::LateClosing;
    }

    public function daysBefore(): int
    {
        return 30;
    }

    public function expectedByDefault(): bool
    {
        return false;
    }

    public function recipient(DocumentContext $context): string
    {
        return 'Sous-préfecture de Louhans';
    }

    public function fields(DocumentContext $context): array
    {
        $event = $context->event;

        return [
            FieldDefinition::text('addressee', 'Madame, Monsieur le Sous-Préfet'),
            FieldDefinition::textarea('recipient', "Madame, Monsieur le Sous-Préfet\nSous-préfecture de Louhans"),
            FieldDefinition::text('until', '3 h'),
            FieldDefinition::text('when', EventPhrases::when($event)),
            FieldDefinition::text('place', $event->getLocation()),
            LetterParts::signerField(),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $details = $context->details();
        $addressee = $values->text('addressee');

        return new DocumentSheet([
            LetterParts::sender($details),
            LetterParts::recipient($values->text('recipient')),
            LetterParts::placeAndDate($context->facts->today),
            LetterParts::subject('demande de dérogation exceptionnelle à l’heure de fermeture'),
            LetterParts::paragraph('salutation', $addressee.','),
            LetterParts::paragraph('event', 'L’association '.$details->officialName.' organise « '.$context->event->getTitle().' », '.$values->text('when').', '.$values->text('place').', avec une buvette temporaire autorisée par la mairie de '.EventPhrases::commune($context->event).'.'),
            LetterParts::paragraph('request', 'Nous sollicitons une dérogation exceptionnelle à l’heure de fermeture des débits de boissons pour cette soirée, jusqu’à '.$values->text('until').'.'),
            LetterParts::paragraph('commitments', 'Nous veillerons à la tranquillité du voisinage et au bon ordre de la soirée, et ne servirons d’alcool ni aux mineurs ni aux personnes en état d’ivresse.'),
            LetterParts::politeness($addressee),
            LetterParts::signature($details, $values->text('signer')),
            LetterParts::note('authority', 'Les horaires des débits de boissons sont fixés par arrêté préfectoral : confirmer auprès de la sous-préfecture de Louhans qui accorde la dérogation (elle ou la mairie) et le délai demandé.'),
        ]);
    }
}
