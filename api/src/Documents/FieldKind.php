<?php

namespace App\Documents;

/** Nature d'un champ du formulaire d'un document (rendu par l'écran). */
enum FieldKind: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Checkbox = 'checkbox';
    case Choice = 'choice';
}
