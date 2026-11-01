<?php

namespace App\Gallery;

/** Photo ou vidéo de galerie : préfixe des actions du journal et des textes des notifications. */
enum MediaKind: string
{
    case Photo = 'photo';
    case Video = 'video';
}
