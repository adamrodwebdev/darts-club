<?php

declare(strict_types=1);

namespace App\Admin;

/** Message du président avant envoi : label (catégorie de la notification), titre et texte */
final readonly class BroadcastDraft
{
    public function __construct(
        public BroadcastLabel $label,
        public string $title,
        public string $body,
    ) {
    }
}
