<?php

namespace App\Gallery;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\ClubEvent;
use App\Entity\EventVideo;
use App\Entity\User;
use App\Enum\VideoFailure;
use App\Enum\VideoStatus;
use App\Media\UploadParts;
use App\Media\VideoContainer;
use App\Media\VideoStorage;
use App\Message\TranscodeVideo;
use App\Repository\EventVideoRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Envoi des vidéos par morceaux de 8 Mo : un téléphone en 4G reprend où il s'était arrêté au lieu
 * de tout renvoyer. Le dernier morceau vérifie la signature du fichier puis confie la conversion
 * au worker ; rien de ce qui est envoyé n'est jamais servi tel quel.
 */
final class VideoUploads
{
    public const MAX_PER_EVENT = 20;

    /** Sous la limite de Caddy pour l'adresse des morceaux (11 Mo) */
    public const CHUNK_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly EventVideoRepository $videos,
        private readonly UploadParts $parts,
        private readonly VideoStorage $videoStorage,
        private readonly MessageBusInterface $bus,
        private readonly AuditTrail $audit,
    ) {
    }

    public function start(ClubEvent $event, User $uploader, UploadDeclaration $declaration): EventVideo
    {
        $event->assertGalleryOpen();
        if ($this->videos->countKeptForEvent($event) >= self::MAX_PER_EVENT) {
            throw new GalleryException('La galerie est limitée à %max% vidéos par événement.', null, ['%max%' => self::MAX_PER_EVENT]);
        }
        if (!$this->parts->hasRoomFor($declaration->size)) {
            throw new GalleryException('Plus assez de place sur le serveur pour cette vidéo : préviens l’administrateur.');
        }

        $video = new EventVideo(event: $event, uploader: $uploader, declaration: $declaration, now: $this->clock->now());
        $this->em->persist($video);
        $this->em->flush();

        return $video;
    }

    /**
     * Ajoute un morceau. La ligne est verrouillée le temps de l'écrire : un morceau renvoyé après
     * une coupure, pendant que l'original arrive encore, ne s'écrit jamais par-dessus.
     *
     * @param resource $body
     */
    public function append(EventVideo $video, int $offset, mixed $body): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
            $video->assertAccepts($offset);
            $written = $this->parts->append(
                upload: $video->getId(),
                offset: $offset,
                body: $body,
                maxBytes: min(self::CHUNK_BYTES, $video->remainingBytes()),
            );
            $video->receive($written);
            if (VideoStatus::Processing === $video->status()) {
                $this->checkSignature($video);
            }
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        if (VideoStatus::Processing === $video->status()) {
            $this->bus->dispatch(new TranscodeVideo($video->getId()->toRfc4122()));
        }
    }

    /** Retirée par son auteur, ou par un organisateur (modération inscrite au journal) */
    public function delete(EventVideo $video, User $actor): void
    {
        if (!$video->isUploadedBy($actor)) {
            $this->audit->record($actor, new AuditEntry('video.delete', $video->getEvent()->getTitle(), ['author' => $video->uploaderName()]));
        }
        $files = $video->storedFiles();
        $this->em->remove($video);
        $this->em->flush();

        $this->parts->discard($video->getId());
        foreach ($files as $id) {
            $this->videoStorage->delete($id);
        }
    }

    /** Fichier complet : MP4, MOV ou WebM reconnu à ses premiers octets, sinon refusé avant tout décodage */
    private function checkSignature(EventVideo $video): void
    {
        if (null === VideoContainer::fromSignature($this->parts->head($video->getId()))) {
            $video->fail(VideoFailure::Unreadable);
            $this->parts->discard($video->getId());
        }
    }
}
