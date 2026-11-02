<?php

namespace App\Gallery;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Droit à l'image : un membre demande le retrait d'une photo ou d'une vidéo, elle disparaît tout de
 * suite de la galerie et la présidence décide (la remettre ou la supprimer). Tout est au journal.
 */
final class GalleryModeration
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly AuditTrail $audit,
    ) {
    }

    public function requestRemoval(GalleryMedia $media, User $member): void
    {
        $media->hideOnRequest($member, $this->clock->now());
        $this->audit->record($member, new AuditEntry($media->kind()->value.'.hide', $media->getEvent()->getTitle(), ['author' => $media->uploaderName()]));
        $this->em->flush();
    }

    public function restore(GalleryMedia $media, User $actor): void
    {
        $media->restore();
        $this->audit->record($actor, new AuditEntry($media->kind()->value.'.restore', $media->getEvent()->getTitle(), ['author' => $media->uploaderName()]));
        $this->em->flush();
    }
}
