<?php

namespace App\Documents;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Les modèles de documents, un par démarche, dans l'ordre de la liste des démarches. */
final class DocumentTemplates
{
    /** @var array<string, DocumentTemplate> */
    private array $byType = [];

    /** @param iterable<DocumentTemplate> $templates */
    public function __construct(
        #[AutowireIterator('app.document_template')]
        iterable $templates,
    ) {
        foreach ($templates as $template) {
            $this->byType[$template->type()->value] = $template;
        }
    }

    public function for(DocumentType $type): DocumentTemplate
    {
        return $this->byType[$type->value] ?? throw new \LogicException(\sprintf('Aucun modèle pour « %s ».', $type->value));
    }

    /** @return list<DocumentTemplate> */
    public function all(): array
    {
        return array_map($this->for(...), DocumentType::cases());
    }
}
