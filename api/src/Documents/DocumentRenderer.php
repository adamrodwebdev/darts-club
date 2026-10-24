<?php

namespace App\Documents;

/** Rend le document d'une démarche : le modèle, rempli pour la soirée, puis les changements du secrétariat. */
final class DocumentRenderer
{
    public function __construct(
        private readonly DocumentTemplates $templates,
    ) {
    }

    public function render(DocumentContext $context, DocumentType $type, ProcedureContent $content): RenderedDocument
    {
        $template = $this->templates->for($type);
        $fields = $template->fields($context);
        $values = FieldValues::from($fields, $content->values);

        return new RenderedDocument($fields, $values, $template->sheet($context, $values), $content->rewrites);
    }
}
