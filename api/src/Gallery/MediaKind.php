<?php

namespace App\Gallery;

/** Photo ou vidéo de galerie : préfixe des actions du journal et texte de la demande de retrait. */
enum MediaKind: string
{
    case Photo = 'photo';
    case Video = 'video';

    /** Clé du texte de la notification envoyée à la présidence (domaine « notifications ») */
    public function removalNoticeKey(): string
    {
        return match ($this) {
            self::Photo => 'gallery.removal.body.photo',
            self::Video => 'gallery.removal.body.video',
        };
    }
}
