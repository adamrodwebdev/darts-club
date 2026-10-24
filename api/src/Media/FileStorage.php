<?php

namespace App\Media;

/** Fichiers privés (justificatifs) rangés hors de la racine web, sous un identifiant opaque. */
interface FileStorage
{
    /**
     * @param 'webp'|'pdf' $extension
     *
     * @return string identifiant du fichier (nom aléatoire et extension)
     */
    public function store(string $binary, string $extension): string;

    /** Chemin local lisible, ou null si absent. */
    public function path(string $id): ?string;

    public function delete(string $id): void;
}
