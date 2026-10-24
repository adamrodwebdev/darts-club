<?php

namespace App\Documents;

/** Document d'une démarche rendu pour une soirée, avec ce que le secrétariat y a changé. */
final readonly class RenderedDocument
{
    /** @var array<string, string> */
    private array $rewrites;

    /**
     * @param list<FieldDefinition> $fields
     * @param array<string, string> $rewrites
     */
    public function __construct(
        public array $fields,
        public FieldValues $values,
        public DocumentSheet $sheet,
        array $rewrites,
    ) {
        $this->rewrites = $sheet->keptRewrites($rewrites);
    }

    /** Ce qui mérite d'être gardé : les champs modifiés et les réécritures qui comptent encore */
    public function content(): ProcedureContent
    {
        return new ProcedureContent($this->values->edits($this->fields), $this->rewrites);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $values = $this->values->all();

        return [
            'fields' => array_map(static fn (FieldDefinition $field): array => $field->toArray($values[$field->key] ?? $field->default), $this->fields),
            'blocks' => $this->sheet->blocksWith($this->rewrites),
            'links' => $this->sheet->linksArray(),
            'tickets' => $this->sheet->tickets?->toArray(),
        ];
    }
}
