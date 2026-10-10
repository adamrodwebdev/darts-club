<?php

namespace App\Profile;

/** Stockage des avatars traités, hors de la racine web. */
interface AvatarStorage
{
    /** @return string identifiant opaque du fichier */
    public function store(string $webpBinary): string;

    /** Chemin local lisible, ou null si absent. */
    public function path(string $id): ?string;

    public function delete(string $id): void;
}
