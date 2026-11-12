<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Versions AVIF des images rangées en WebP : 20 à 40 % plus légères, servies aux navigateurs qui
 * les annoncent (en-tête Accept). Le WebP reste pour les autres.
 */
interface AvifVariants
{
    public function storeAvif(string $id, string $avifBinary): void;

    /** Chemin de la version AVIF de l'image $id, ou null si elle n'existe pas. */
    public function avifPath(string $id): ?string;
}
