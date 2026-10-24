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
use App\Documents\OfficialLink;

/** Demande d'occupation temporaire du domaine public (terrasse, rue, place) au gestionnaire du lieu. */
final class PublicSpaceTemplate implements DocumentTemplate
{
    public function type(): DocumentType
    {
        return DocumentType::PublicSpace;
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
        return 'Mairie de '.EventPhrases::commune($context->event);
    }

    public function fields(DocumentContext $context): array
    {
        $event = $context->event;

        return [
            FieldDefinition::text('commune', EventPhrases::commune($event)),
            FieldDefinition::text('spot', 'devant '.$event->getLocation()),
            FieldDefinition::text('surface', ''),
            FieldDefinition::text('installation', 'des tables, des chaises et un barnum'),
            FieldDefinition::text('when', EventPhrases::when($event)),
            LetterParts::signerField(),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $details = $context->details();
        $surface = $values->text('surface');

        return new DocumentSheet([
            LetterParts::sender($details),
            LetterParts::recipient("Madame, Monsieur le Maire\nMairie de ".$values->text('commune')),
            LetterParts::placeAndDate($context->facts->today),
            LetterParts::subject('demande d’autorisation d’occupation temporaire du domaine public'),
            LetterParts::paragraph('salutation', 'Madame, Monsieur le Maire,'),
            LetterParts::paragraph('request', 'L’association '.$details->officialName.' sollicite l’autorisation d’occuper temporairement le domaine public '.$values->text('spot').('' === $surface ? '' : ' ('.$surface.')').', '.$values->text('when').', pour y installer '.$values->text('installation').' à l’occasion de « '.$context->event->getTitle().' ».'),
            LetterParts::paragraph('commitments', 'L’association est assurée en responsabilité civile. Nous nous engageons à laisser le passage libre aux secours et aux riverains, et à rendre les lieux propres.'),
            LetterParts::politeness('Madame, Monsieur le Maire'),
            LetterParts::signature($details, $values->text('signer')),
            LetterParts::note('forms', 'La mairie peut demander le formulaire Cerfa 14023*01 ; pour fermer ou dévier une rue, le Cerfa 14024*01.'),
        ], [
            new OfficialLink('Formulaire Cerfa 14023*01 (permission de voirie)', 'https://www.formulaires.service-public.gouv.fr/gf/showFormulaireSignaletiqueConsulter.do?numCerfaAndExtension=14023'),
            new OfficialLink('Formulaire Cerfa 14024*01 (arrêté de circulation)', 'https://www.formulaires.service-public.gouv.fr/gf/showFormulaireSignaletiqueConsulter.do?numCerfaAndExtension=14024'),
        ]);
    }
}
