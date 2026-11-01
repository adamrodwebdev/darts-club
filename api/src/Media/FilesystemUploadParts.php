<?php

namespace App\Media;

use App\Gallery\GalleryException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

/** Déclaré dans config/services.yaml avec son dossier (var/storage/video-uploads). */
#[Exclude]
final class FilesystemUploadParts implements UploadParts
{
    /** Place gardée libre pour le site, la base et les sauvegardes */
    private const RESERVE_BYTES = 1024 ** 3;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function hasRoomFor(int $bytes): bool
    {
        $this->filesystem->mkdir($this->directory);
        $free = disk_free_space($this->directory);

        // L'original, son MP4 et sa version AV1 coexistent pendant la conversion
        return false !== $free && $free > 3 * $bytes + self::RESERVE_BYTES;
    }

    public function append(Uuid $upload, int $offset, mixed $body, int $maxBytes): int
    {
        if (!\is_resource($body) || $offset < 0) {
            throw new \InvalidArgumentException('Morceau illisible.');
        }

        $this->filesystem->mkdir($this->directory);
        // « c » : crée le fichier au besoin, sans le vider
        $handle = fopen($this->path($upload), 'c');
        if (false === $handle) {
            throw new \RuntimeException('Envoi impossible à écrire.');
        }

        try {
            ftruncate($handle, $offset);
            fseek($handle, $offset);
            $written = stream_copy_to_stream($body, $handle, $maxBytes + 1);
            if (false === $written || $written < 1) {
                ftruncate($handle, $offset);
                throw new GalleryException('Morceau vide : réessaie l’envoi.');
            }
            if ($written > $maxBytes) {
                ftruncate($handle, $offset);
                throw new GalleryException('Morceau trop grand : %max% octets au plus.', null, ['%max%' => $maxBytes]);
            }
            fflush($handle);

            return $written;
        } finally {
            fclose($handle);
        }
    }

    public function head(Uuid $upload): string
    {
        $handle = @fopen($this->path($upload), 'rb');
        if (false === $handle) {
            return '';
        }
        $head = fread($handle, VideoContainer::SIGNATURE_BYTES);
        fclose($handle);

        return false === $head ? '' : $head;
    }

    public function path(Uuid $upload): string
    {
        return $this->directory.'/'.$upload->toRfc4122().'.part';
    }

    public function discard(Uuid $upload): void
    {
        $this->filesystem->remove($this->path($upload));
    }
}
