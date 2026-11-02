<?php

namespace App\Media;

use App\Gallery\GalleryException;
use Symfony\Component\Uid\Uuid;

/** Fichiers des envois de vidéos en cours, reçus morceau par morceau, hors de la racine web. */
interface UploadParts
{
    /** Assez de place pour un envoi de $bytes octets et ses conversions (avec une marge pour le site) */
    public function hasRoomFor(int $bytes): bool;

    /**
     * Ajoute un morceau à l'envoi, à partir de $offset : le fichier est d'abord ramené à cette taille,
     * un morceau interrompu ne laisse rien. Lit au plus $maxBytes octets du corps de la requête.
     *
     * @param resource $body
     *
     * @return int<1, max> octets écrits
     *
     * @throws GalleryException morceau vide, ou plus grand que $maxBytes
     */
    public function append(Uuid $upload, int $offset, mixed $body, int $maxBytes): int;

    /** Premiers octets du fichier envoyé (signature du conteneur) */
    public function head(Uuid $upload): string;

    public function path(Uuid $upload): string;

    public function discard(Uuid $upload): void;
}
