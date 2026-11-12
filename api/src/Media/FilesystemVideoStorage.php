<?php

declare(strict_types=1);

namespace App\Media;

use App\Security\TokenGenerator;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Filesystem;

/** Déclaré dans config/services.yaml avec son dossier (var/storage/event-videos). */
#[Exclude]
final class FilesystemVideoStorage implements VideoStorage
{
    private const ID_PATTERN = '/^[a-f0-9]{32}\.(mp4|webp|avif)$/';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
        private readonly TokenGenerator $tokens,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function import(string $localPath, string $extension): string
    {
        $id = $this->newId($extension);
        $this->filesystem->mkdir($this->directory);
        $this->filesystem->rename($localPath, $this->directory.'/'.$id);

        return $id;
    }

    public function store(string $binary, string $extension): string
    {
        $id = $this->newId($extension);
        $this->filesystem->dumpFile($this->directory.'/'.$id, $binary);

        return $id;
    }

    public function path(string $id): ?string
    {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $file = $this->directory.'/'.$id;

        return is_file($file) ? $file : null;
    }

    public function delete(string $id): void
    {
        if (1 === preg_match(self::ID_PATTERN, $id)) {
            $this->filesystem->remove($this->directory.'/'.$id);
        }
    }

    /** Nom aléatoire : jamais dérivé du nom ou du contenu envoyé */
    private function newId(string $extension): string
    {
        $id = $this->tokens->hex(16).'.'.$extension;
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw new \LogicException('Extension de fichier non prise en charge.');
        }

        return $id;
    }
}
