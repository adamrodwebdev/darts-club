<?php

namespace App\Gallery;

use App\Entity\EventVideo;
use App\Enum\VideoFailure;
use App\Enum\VideoStatus;
use App\Media\ImageProcessor;
use App\Media\StoredVideo;
use App\Media\TranscodedVideo;
use App\Media\UploadParts;
use App\Media\VideoContainer;
use App\Media\VideoPoster;
use App\Media\VideoRejected;
use App\Media\VideoSource;
use App\Media\VideoStorage;
use App\Media\VideoTranscoder;
use App\Message\CompressVideo;
use App\Message\TranscodeVideo;
use App\Repository\EventVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Première conversion, dans le worker : MP4 H.264 et image d'aperçu (WebP, et AVIF plus légère).
 * La vidéo est lisible dès qu'elle est finie ; la version AV1 suit dans un second message.
 */
#[AsMessageHandler]
final class TranscodeVideoHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventVideoRepository $videos,
        private readonly UploadParts $parts,
        private readonly VideoTranscoder $transcoder,
        private readonly VideoStorage $videoStorage,
        private readonly ImageProcessor $images,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(TranscodeVideo $message): void
    {
        $video = $this->videos->find($message->videoId);
        if (null === $video || VideoStatus::Processing !== $video->status()) {
            return;
        }

        try {
            $result = $this->transcoder->transcode($this->source($video));
        } catch (VideoRejected $e) {
            $video->fail($e->failure);
            $this->em->flush();
            $this->parts->discard($video->getId());

            return;
        }

        $stored = $this->store($result);
        // Supprimée pendant la conversion : rien ne doit rester derrière elle
        if (!$this->videos->exists($video->getId())) {
            $this->discard($stored);
            $this->parts->discard($video->getId());

            return;
        }

        $video->publish($stored);
        $this->em->flush();
        $this->bus->dispatch(new CompressVideo($video->getId()->toRfc4122()));
    }

    /** @throws VideoRejected fichier qui n'est ni MP4/MOV ni WebM/MKV */
    private function source(EventVideo $video): VideoSource
    {
        $container = VideoContainer::fromSignature($this->parts->head($video->getId()));
        if (null === $container) {
            throw new VideoRejected(VideoFailure::Unreadable);
        }

        return new VideoSource($this->parts->path($video->getId()), $container);
    }

    private function store(TranscodedVideo $result): StoredVideo
    {
        // L'aperçu AVIF se tire du WebP que l'on vient de produire, avant de le ranger
        $posterAvif = $this->images->avif($result->posterPath);
        $size = filesize($result->videoPath);

        return new StoredVideo(
            fileId: $this->videoStorage->import($result->videoPath, 'mp4'),
            fileSize: false === $size ? 0 : $size,
            poster: new VideoPoster(
                webpId: $this->videoStorage->import($result->posterPath, 'webp'),
                avifId: null === $posterAvif ? null : $this->videoStorage->store($posterAvif, 'avif'),
            ),
            probe: $result->probe,
        );
    }

    private function discard(StoredVideo $stored): void
    {
        foreach (array_filter([$stored->fileId, $stored->poster->webpId, $stored->poster->avifId]) as $id) {
            $this->videoStorage->delete($id);
        }
    }
}
