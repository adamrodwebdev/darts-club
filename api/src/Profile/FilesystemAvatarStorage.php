<?php

namespace App\Profile;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsAlias(AvatarStorage::class)]
final class FilesystemAvatarStorage implements AvatarStorage
{
    private const ID_PATTERN = '/^[a-f0-9]{32}$/';

    private readonly Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/storage/avatars')]
        private readonly string $directory,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function store(string $webpBinary): string
    {
        // Nom aléatoire : jamais dérivé du nom ou du contenu envoyé
        $id = bin2hex(random_bytes(16));
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
            $this->filesystem->remove($this->file($id));
        }
    }

    private function file(string $id): string
    {
        return $this->directory.'/'.$id.'.webp';
    }
}
