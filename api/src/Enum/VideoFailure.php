<?php

namespace App\Enum;

/** Raison d'une vidéo refusée à la conversion ; le front l'explique dans la langue de l'adhérent. */
enum VideoFailure: string
{
    /** Plus longue que la limite, une fois mesurée par le serveur */
    case TooLong = 'trop-longue';
    /** Image plus grande que la 4K : refusée avant d'être décodée */
    case Oversized = 'trop-grande';
    /** Pas une vidéo MP4, MOV ou WebM, flux illisible, ou conversion impossible */
    case Unreadable = 'illisible';
}
