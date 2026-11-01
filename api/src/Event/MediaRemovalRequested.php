<?php

namespace App\Event;

use App\Gallery\MediaKind;
use Symfony\Component\Uid\Uuid;

/** Un membre demande le retrait d'une photo ou d'une vidéo : masquée, la présidence est prévenue. */
final readonly class MediaRemovalRequested implements DomainEvent
{
    public function __construct(
        public Uuid $eventId,
        public MediaKind $kind,
        public string $requesterName,
    ) {
    }
}
