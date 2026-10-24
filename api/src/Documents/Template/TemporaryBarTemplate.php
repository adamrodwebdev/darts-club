<?php

namespace App\Documents\Template;

use App\ClubEvent\ClubTime;
use App\Documents\DocumentContext;
use App\Documents\DocumentSheet;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentType;
use App\Documents\EventPhrases;
use App\Documents\FieldDefinition;
use App\Documents\FieldValues;
use App\Documents\FrenchDates;
use App\Documents\LetterParts;
use App\Documents\OfficialLink;
use App\EventAccounts\AccountsFlags;

/**
 * Demande d'autorisation de buvette temporaire au maire de la commune de la soirée (Code de la santé
 * publique, art. L3334-2) : boissons des groupes 1 et 3 seulement, cinq autorisations par an.
 */
final class TemporaryBarTemplate implements DocumentTemplate
{
    private const GROUP_3 = 'vins, bières, cidres, poirés, hydromels, vins doux naturels, crèmes de cassis, apéritifs à base de vin et liqueurs de fraise, framboise, cassis ou cerise titrant 18 degrés au plus';

    public function type(): DocumentType
    {
        return DocumentType::TemporaryBar;
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
        return 'Mairie de '.EventPhrases::commune($context->event);
    }

    public function fields(DocumentContext $context): array
    {
        return [
            FieldDefinition::text('commune', EventPhrases::commune($context->event)),
            FieldDefinition::text('when', EventPhrases::when($context->event)),
            FieldDefinition::text('place', $context->event->getLocation()),
            FieldDefinition::checkbox('group3', true),
            LetterParts::signerField(),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $details = $context->details();
        $event = $context->event;
        $drinks = 'des boissons du groupe 1 (boissons sans alcool)'.($values->flag('group3') ? ' et du groupe 3 ('.self::GROUP_3.')' : '');
        $year = $event->getStartsAt()->setTimezone(new \DateTimeZone(ClubTime::TIMEZONE))->format('Y');

        return new DocumentSheet([
            LetterParts::sender($details),
            LetterParts::recipient("Madame, Monsieur le Maire\nMairie de ".$values->text('commune')),
            LetterParts::placeAndDate($context->facts->today),
            LetterParts::subject('demande d’autorisation d’ouverture temporaire d’un débit de boissons'),
            LetterParts::paragraph('salutation', 'Madame, Monsieur le Maire,'),
            LetterParts::paragraph('event', 'L’association '.$details->officialName.' organise « '.$event->getTitle().' », '.$values->text('when').', '.$values->text('place').'.'),
            LetterParts::paragraph('request', 'En application de l’article L3334-2 du Code de la santé publique, nous sollicitons l’autorisation d’y tenir une buvette temporaire servant '.$drinks.'.'),
            LetterParts::paragraph('count', 'Il s’agit de la '.FrenchDates::ordinal($context->facts->earlierTemporaryBars + 1).' demande de l’association pour l’année '.$year.'.'),
            LetterParts::politeness('Madame, Monsieur le Maire'),
            LetterParts::signature($details, $values->text('signer')),
            LetterParts::note('limits', $context->facts->earlierTemporaryBars >= AccountsFlags::TEMPORARY_BARS_PER_YEAR
                ? 'Attention : l’association a déjà eu '.$context->facts->earlierTemporaryBars.' buvettes temporaires cette année, la limite est de '.AccountsFlags::TEMPORARY_BARS_PER_YEAR.'. Pas d’alcools forts dans une buvette temporaire.'
                : 'Cinq autorisations par association et par an ; pas d’alcools forts dans une buvette temporaire. Demande conseillée au moins 15 jours avant.'),
        ], [new OfficialLink('Fiche service-public : buvette temporaire', 'https://www.service-public.gouv.fr/associations/vosdroits/F24345')]);
    }
}
