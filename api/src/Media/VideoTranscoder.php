<?php

namespace App\Media;

/** Conversion des vidéos de galerie (ffmpeg en production, un double dans les tests). */
interface VideoTranscoder
{
    /** Une vidéo dure 60 s au plus ; une seconde de marge pour l'arrondi des téléphones */
    public const MAX_DURATION_MS = 61_000;

    /** Plus grand côté accepté avant décodage (4K, portrait compris) */
    public const MAX_SIDE = 4096;

    /**
     * Convertit la vidéo en MP4 H.264 lisible partout (petit côté de 720 px au plus, 30 images par
     * seconde au plus, sans métadonnées) et tire son image d'aperçu en WebP. Les fichiers produits
     * sont temporaires : l'appelant les range ou les supprime.
     *
     * @throws VideoRejected vidéo illisible, trop grande ou trop longue une fois mesurée
     */
    public function transcode(VideoSource $source): TranscodedVideo;

    /**
     * Version AV1 de la même vidéo, environ deux fois plus légère, lue par les navigateurs qui la
     * décodent (les autres gardent le H.264). Null si le serveur n'a pas d'encodeur AV1.
     *
     * @throws VideoRejected conversion impossible
     */
    public function compressAv1(VideoSource $source): ?string;
}
