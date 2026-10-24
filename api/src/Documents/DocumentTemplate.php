<?php

namespace App\Documents;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Modèle d'un document (un par démarche) : champs pré-remplis, destinataire, délai, puis le texte.
 * Les textes sont en français : ils partent vers des administrations françaises, quelle que soit la
 * langue de l'écran (dont les libellés, eux, sont traduits).
 */
#[AutoconfigureTag('app.document_template')]
interface DocumentTemplate
{
    public function type(): DocumentType;

    /** Délai conseillé : nombre de jours avant la soirée, null s'il n'y en a pas */
    public function daysBefore(): ?int;

    /** Démarche utile à la plupart des soirées ; sinon « pas pour cette soirée » d'office */
    public function expectedByDefault(): bool;

    /** Qui reçoit ou délivre le document */
    public function recipient(DocumentContext $context): string;

    /** @return list<FieldDefinition> */
    public function fields(DocumentContext $context): array;

    public function sheet(DocumentContext $context, FieldValues $values): DocumentSheet;
}
