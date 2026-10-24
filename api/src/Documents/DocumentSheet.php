<?php

namespace App\Documents;

/**
 * Document prêt à imprimer : morceaux de texte (courrier ou fiche), liens officiels et, pour les
 * billets, la série à imprimer. Les textes réécrits par le secrétariat remplacent ceux du modèle,
 * morceau par morceau.
 */
final readonly class DocumentSheet
{
    /**
     * @param list<DocumentBlock> $blocks
     * @param list<OfficialLink>  $links
     */
    public function __construct(
        public array $blocks,
        public array $links = [],
        public ?TicketSeries $tickets = null,
    ) {
    }

    /**
     * @param array<string, string> $rewrites textes réécrits, par identifiant de morceau
     *
     * @return list<array<string, mixed>>
     */
    public function blocksWith(array $rewrites): array
    {
        return array_map(
            static fn (DocumentBlock $block): array => isset($rewrites[$block->id])
                ? $block->rewritten($rewrites[$block->id])->toArray(true)
                : $block->toArray(false),
            $this->blocks,
        );
    }

    /**
     * Réécritures qui comptent encore : elles visent un morceau du document (une ligne du bilan peut
     * disparaître) et en changent le texte.
     *
     * @param array<string, string> $rewrites
     *
     * @return array<string, string>
     */
    public function keptRewrites(array $rewrites): array
    {
        $kept = [];
        foreach ($this->blocks as $block) {
            $text = isset($rewrites[$block->id]) ? $block->rewritten($rewrites[$block->id])->text : $block->text;
            if ($text !== $block->text) {
                $kept[$block->id] = $text;
            }
        }

        return $kept;
    }

    /** @return list<array{label: string, url: string}> */
    public function linksArray(): array
    {
        return array_map(static fn (OfficialLink $link): array => ['label' => $link->label, 'url' => $link->url], $this->links);
    }
}
