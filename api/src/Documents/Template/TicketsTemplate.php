<?php

namespace App\Documents\Template;

use App\Documents\BlockKind;
use App\Documents\DocumentBlock;
use App\Documents\DocumentContext;
use App\Documents\DocumentSheet;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentType;
use App\Documents\FieldDefinition;
use App\Documents\FieldValues;
use App\Documents\FrenchDates;
use App\Documents\LetterParts;
use App\Documents\TicketSeries;

/**
 * Billets d'entrée numérotés avec leurs souches (code général des impôts, annexe IV, art. 50 sexies B) :
 * la série se règle ici, l'écran l'imprime ; la fiche rappelle les mentions et la conservation.
 */
final class TicketsTemplate implements DocumentTemplate
{
    public function type(): DocumentType
    {
        return DocumentType::Tickets;
    }

    public function daysBefore(): int
    {
        return 7;
    }

    public function expectedByDefault(): bool
    {
        return false;
    }

    public function recipient(DocumentContext $context): string
    {
        return 'Entrée de la soirée';
    }

    public function fields(DocumentContext $context): array
    {
        return [
            FieldDefinition::text('count', '100'),
            FieldDefinition::text('first', '1'),
            FieldDefinition::text('price', '2,00 €'),
            FieldDefinition::text('category', 'Entrée'),
        ];
    }

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet
    {
        $event = $context->event;
        $tickets = new TicketSeries(
            organizer: $context->details()->officialName,
            event: $event->getTitle().' · '.FrenchDates::day($event->getStartsAt()),
            category: $values->text('category'),
            price: '' === $values->text('price') ? 'Gratuit' : $values->text('price'),
            first: (int) $values->text('first'),
            count: (int) $values->text('count'),
        );

        return new DocumentSheet([
            new DocumentBlock('heading', BlockKind::Heading, 'Billets d’entrée numérotés'),
            new DocumentBlock('series', BlockKind::Item, 'n° '.$tickets->first.' à '.$tickets->last().' ('.$tickets->count.' billets) · '.$tickets->category.' · '.$tickets->price, 'Série'),
            LetterParts::note('mentions', 'Chaque billet et sa souche portent le nom de l’organisateur, un numéro d’une série continue, la catégorie et le prix payé (ou « gratuit »).'),
            LetterParts::note('stubs', 'Garder les souches jusqu’au 31 décembre de l’année suivante et noter, après la soirée, les numéros vendus et la recette par catégorie.'),
        ], tickets: $tickets);
    }
}
