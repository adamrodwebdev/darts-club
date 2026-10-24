<?php

namespace App\Media;

use App\Security\TokenGenerator;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemFileStorage implements FileStorage
{
    private const ID_PATTERN = '/^[a-f0-9]{32}\.(webp|pdf)$/';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
        private readonly TokenGenerator $tokens,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function store(string $binary, string $extension): string
    {
        // Nom aléatoire : jamais dérivé du nom ou du contenu envoyé
        $id = $this->tokens->hex(16).'.'.$extension;
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw new \LogicException('Extension de fichier non prise en charge.');
        }
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
}
