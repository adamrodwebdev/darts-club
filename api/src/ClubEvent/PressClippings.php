<?php

declare(strict_types=1);

namespace App\ClubEvent;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\EventPhoto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Dans la presse » d'un événement : qui l'organise classe une photo de la galerie parmi les
 * coupures de presse (journal, date de parution, titre, crédit), en corrige les détails ou la rend
 * à la galerie. Chaque changement est inscrit au journal.
 */
final class PressClippings
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditTrail $audit,
    ) {
    }

    public function mark(EventPhoto $photo, PressDetails $details, User $actor): void
    {
        $photo->markAsPressClipping($details);
        $this->audit->record($actor, new AuditEntry('photo.press', $photo->getEvent()->getTitle(), ['author' => $photo->uploaderName()]));
        $this->em->flush();
    }

    /** Une photo qui n'est pas une coupure reste telle quelle, sans ligne au journal */
    public function unmark(EventPhoto $photo, User $actor): void
    {
        if (!$photo->isPressClipping()) {
            return;
        }
        $photo->returnToGallery();
        $this->audit->record($actor, new AuditEntry('photo.unpress', $photo->getEvent()->getTitle(), ['author' => $photo->uploaderName()]));
        $this->em->flush();
    }
}
