<?php

namespace App\Media;

/**
 * Conteneurs acceptés, reconnus à leur signature et non à leur nom : ffmpeg reçoit le démultiplexeur
 * imposé (-f), jamais sa détection automatique, qui ouvrirait des listes de lecture (HLS, concat)
 * capables de lire d'autres fichiers du serveur.
 */
enum VideoContainer: string
{
    public const SIGNATURE_BYTES = 12;

    /** MP4, MOV (iPhone), 3GP, M4V : boîte « ftyp » à l'octet 4 */
    case IsoMedia = 'mov';
    /** WebM, MKV : en-tête EBML */
    case Matroska = 'matroska';

    /** D'après les premiers octets du fichier ; null si ce n'est aucun des conteneurs acceptés. */
    public static function fromSignature(string $head): ?self
    {
        return match (true) {
            \strlen($head) >= 8 && 'ftyp' === substr($head, 4, 4) => self::IsoMedia,
            \strlen($head) >= 4 && "\x1A\x45\xDF\xA3" === substr($head, 0, 4) => self::Matroska,
            default => null,
        };
    }
}
