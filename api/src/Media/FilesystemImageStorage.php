<?php

declare(strict_types=1);

namespace App\Media;

use App\Security\TokenGenerator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Déclaré explicitement par dossier dans config/services.yaml. La version AVIF d'une image, quand
 * elle existe, se range à côté de son WebP ({id}.avif) et disparaît avec lui.
 */
#[Exclude]
final class FilesystemImageStorage implements ImageStorage, AvifVariants
{
    private const ID_PATTERN = '/^[a-f0-9]{32}$/';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
        private readonly TokenGenerator $tokens,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function store(string $webpBinary): string
    {
        // Nom aléatoire : jamais dérivé du nom ou du contenu envoyé
        $id = $this->tokens->hex(16);
        $this->filesystem->dumpFile($this->file($id), $webpBinary);

        return $id;
    }

    public function path(string $id): ?string
    {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $file = $this->file($id);

        return is_file($file) ? $file : null;
    }

    public function delete(string $id): void
    {
        if (1 === preg_match(self::ID_PATTERN, $id)) {
            $this->filesystem->remove([$this->file($id), $this->file($id, 'avif')]);
        }
    }

    public function storeAvif(string $id, string $avifBinary): void
    {
        if (1 === preg_match(self::ID_PATTERN, $id) && is_file($this->file($id))) {
            $this->filesystem->dumpFile($this->file($id, 'avif'), $avifBinary);
        }
    }

    public function avifPath(string $id): ?string
    {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $file = $this->file($id, 'avif');

        return is_file($file) ? $file : null;
    }

    private function file(string $id, string $extension = 'webp'): string
    {
        return $this->directory.'/'.$id.'.'.$extension;
    }
}
