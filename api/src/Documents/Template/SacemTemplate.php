<?php

namespace App\Documents\Template;

use App\Documents\BlockKind;
use App\Documents\DocumentBlock;
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

/**
 * Fiche de préparation de la demande d'autorisation SACEM : elle se fait en ligne, cette fiche
 * réunit ce qu'il faut y saisir. Faite au moins 15 jours avant, elle donne 20 % de réduction.
 */
final class SacemTemplate implements DocumentTemplate
{
    private const KINDS = [
        'musique-enregistree' => 'Soirée avec musique enregistrée',
        'concert' => 'Concert',
        'bal' => 'Bal ou soirée dansante',
    ];

    public function type(): DocumentType
    {
        return DocumentType::Sacem;
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
        return 'SACEM (en ligne)';
    }

    public function fields(DocumentContext $context): array
    {
        return [
            FieldDefinition::choice('kind', array_keys(self::KINDS), 'musique-enregistree'),
            FieldDefinition::text('attendance', EventPhrases::attendance($context->event)),
            FieldDefinition::text('entry', 'Entrée libre'),
            FieldDefinition::textarea('programme', ''),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $details = $context->details();
        $event = $context->event;
        $accounts = $context->accounts();
        $deadline = $event->getStartsAt()->modify('-15 days');

        $item = static fn (string $id, string $label, string $text): DocumentBlock => new DocumentBlock($id, BlockKind::Item, $text, $label);

        return new DocumentSheet([
            new DocumentBlock('heading', BlockKind::Heading, 'Demande d’autorisation SACEM : informations à saisir en ligne'),
            $item('organizer', 'Organisateur', $details->officialName.' (association loi 1901'.(null === $details->rnaNumber ? '' : ', RNA '.$details->rnaNumber).')'),
            $item('event', 'Manifestation', $event->getTitle()),
            $item('when', 'Date et horaires', EventPhrases::when($event)),
            $item('place', 'Lieu', $event->getLocation()),
            $item('kind', 'Type', self::KINDS[$values->text('kind')] ?? self::KINDS['musique-enregistree']),
            $item('attendance', 'Public attendu', '' === $values->text('attendance') ? '[nombre de personnes]' : $values->text('attendance').' personnes'),
            $item('entry', 'Prix d’entrée', $values->text('entry')),
            $item('budget', 'Budget des dépenses prévu', null === $accounts ? '[à remplir dans le bilan de la soirée]' : EventPhrases::money($accounts->plannedCosts())),
            $item('programme', 'Œuvres jouées', '' === $values->text('programme') ? '[liste des morceaux et de leurs auteurs]' : $values->text('programme')),
            LetterParts::note('deadline', 'Demande faite au moins 15 jours avant (au plus tard le '.FrenchDates::date($deadline).') : 20 % de réduction sur les droits.'),
            LetterParts::note('after', 'Après la soirée : déclarer le programme exact et les recettes, puis régler les droits.'),
        ], [new OfficialLink('Espace client SACEM (autorisations)', 'https://clients.sacem.fr/autorisations')]);
    }
}
