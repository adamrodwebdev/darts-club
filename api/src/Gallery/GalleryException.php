<?php

namespace App\Gallery;

use App\Translation\UnprocessableException;

/** Envoi refusé par la galerie (limites, état de l'envoi) : message traduit, réponse 422. */
final class GalleryException extends UnprocessableException
{
}
