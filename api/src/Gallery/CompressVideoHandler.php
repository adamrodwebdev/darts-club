<?php

namespace App\Gallery;

use App\Media\UploadParts;
use App\Media\VideoContainer;
use App\Media\VideoRejected;
use App\Media\VideoSource;
use App\Media\VideoStorage;
use App\Media\VideoTranscoder;
use App\Message\CompressVideo;
use App\Repository\EventVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Seconde conversion, depuis l'original : AV1, environ deux fois plus léger que le H.264, gardé
 * seulement s'il l'est vraiment. L'original envoyé est effacé ensuite, quoi qu'il arrive.
 */
#[AsMessageHandler]
final class CompressVideoHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventVideoRepository $videos,
        private readonly UploadParts $parts,
        private readonly VideoTranscoder $transcoder,
        private readonly VideoStorage $videoStorage,
    ) {
    }

    public function __invoke(CompressVideo $message): void
    {
        $video = $this->videos->find($message->videoId);
        if (null === $video) {
            return;
        }

        try {
            $container = VideoContainer::fromSignature($this->parts->head($video->getId()));
            if (!$video->isReady() || null !== $video->av1FileId() || null === $container) {
                return;
            }

            $av1 = $this->transcoder->compressAv1(new VideoSource($this->parts->path($video->getId()), $container));
            if (null === $av1) {
                return;
            }

            $size = filesize($av1);
            if (false === $size || $size >= (int) $video->fileSize() || !$this->videos->exists($video->getId())) {
                (new Filesystem())->remove($av1);

                return;
            }

            $video->attachAv1($this->videoStorage->import($av1, 'mp4'));
            $this->em->flush();
        } catch (VideoRejected) {
            // Le H.264 reste : la vidéo est déjà lisible partout
        } finally {
            $this->parts->discard($video->getId());
        }
    }
}
