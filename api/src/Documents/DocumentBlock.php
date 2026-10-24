<?php

namespace App\Documents;

/**
 * Morceau d'un document, repéré par un identifiant stable : le secrétariat peut en réécrire le texte.
 * Le texte suit la typographie française (espaces insécables), qu'il vienne du modèle ou d'une réécriture.
 */
final readonly class DocumentBlock
{
    public string $text;

    public function __construct(
        public string $id,
        public BlockKind $kind,
        string $text,
        /** Intitulé d'une ligne de fiche */
        public ?string $label = null,
    ) {
        $this->text = FrenchTypography::apply($text);
    }

    public function rewritten(string $text): self
    {
        return new self($this->id, $this->kind, $text, $this->label);
    }

    /** @return array<string, mixed> */
    public function toArray(bool $rewritten): array
    {
        return ['id' => $this->id, 'kind' => $this->kind->value, 'text' => $this->text, 'label' => $this->label, 'rewritten' => $rewritten];
    }
}
