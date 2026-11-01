<?php

namespace App\Media;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Réponse d'image au format le plus léger que le navigateur lit : AVIF s'il l'annonce dans son
 * en-tête Accept et qu'une version existe, WebP sinon. Même adresse pour les deux (Vary: Accept),
 * privée (réservée aux membres), gardée 30 jours.
 */
final class NegotiatedImage
{
    public static function response(Request $request, ImageFiles $files): BinaryFileResponse
    {
        $avif = null !== $files->avifPath && str_contains((string) $request->headers->get('Accept'), 'image/avif');

        $response = new BinaryFileResponse($avif ? (string) $files->avifPath : $files->webpPath, headers: [
            'Content-Type' => $avif ? 'image/avif' : 'image/webp',
        ]);
        $response->setPrivate();
        $response->setMaxAge(30 * 86400);
        $response->setVary('Accept', false);
        $response->setEtag($files->etag.($avif ? '-avif' : '-webp'));
        $response->isNotModified($request);

        return $response;
    }
}
