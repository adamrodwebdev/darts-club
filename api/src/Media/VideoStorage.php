<?php

declare(strict_types=1);

namespace App\Media;

/** Fichiers des vidéos converties (MP4, aperçus), hors de la racine web, sous un nom aléatoire. */
interface VideoStorage
{
    /**
     * Range un fichier produit par la conversion : déplacé, pas copié.
     *
     * @param 'mp4'|'webp' $extension
     *
     * @return string identifiant du fichier (nom aléatoire et extension)
     */
    public function import(string $localPath, string $extension): string;

    /**
     * Range un fichier produit en mémoire (aperçu AVIF).
     *
     * @param 'avif' $extension
     */
    public function store(string $binary, string $extension): string;

    /** Chemin local lisible, ou null si absent. */
    public function path(string $id): ?string;

    public function delete(string $id): void;
}
