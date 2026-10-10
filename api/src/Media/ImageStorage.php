<?php

namespace App\Media;

/**
 * Stockage d'images traitées, hors de la racine web.
 * Instances nommées : $avatarStorage, $eventPhotoStorage (config/services.yaml).
 */
interface ImageStorage
{
    /** @return string identifiant opaque du fichier */
    public function store(string $webpBinary): string;

    /** Chemin local lisible, ou null si absent. */
    public function path(string $id): ?string;

    public function delete(string $id): void;
}
