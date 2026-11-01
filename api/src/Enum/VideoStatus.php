<?php

namespace App\Enum;

/** Étapes d'une vidéo de galerie : envoi par morceaux, conversion par le worker, puis lecture. */
enum VideoStatus: string
{
    case Uploading = 'envoi';
    case Processing = 'conversion';
    case Ready = 'prete';
    case Failed = 'echec';
}
