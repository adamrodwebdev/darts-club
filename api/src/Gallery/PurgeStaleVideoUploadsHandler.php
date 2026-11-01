<?php

namespace App\Gallery;

use App\Enum\VideoFailure;
use App\Enum\VideoStatus;
use App\Media\UploadParts;
use App\Repository\EventVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Un envoi resté inachevé un jour est abandonné ; une conversion bloquée un jour (worker arrêté)
 * passe en échec ; un échec est effacé au bout de 7 jours. Le disque ne garde rien d'inutile.
 */
#[AsMessageHandler]
final class PurgeStaleVideoUploadsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly EventVideoRepository $videos,
        private readonly UploadParts $parts,
    ) {
    }

    public function __invoke(PurgeStaleVideoUploads $message): void
    {
        $now = $this->clock->now();

        foreach ($this->videos->staleBefore(VideoStatus::Uploading, $now->modify('-1 day')) as $video) {
            $this->parts->discard($video->getId());
            $this->em->remove($video);
        }
        foreach ($this->videos->staleBefore(VideoStatus::Processing, $now->modify('-1 day')) as $video) {
            $video->fail(VideoFailure::Unreadable);
            $this->parts->discard($video->getId());
        }
        foreach ($this->videos->staleBefore(VideoStatus::Failed, $now->modify('-7 days')) as $video) {
            $this->parts->discard($video->getId());
            $this->em->remove($video);
        }

        $this->em->flush();
    }
}
