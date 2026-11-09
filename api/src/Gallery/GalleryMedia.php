<?php

declare(strict_types=1);

namespace App\Gallery;

use App\Entity\ClubEvent;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

/** Photo ou vidéo de la galerie d'un événement, réservée aux membres validés. */
interface GalleryMedia
{
    public function getId(): Uuid;

    public function getEvent(): ClubEvent;

    public function kind(): MediaKind;

    public function isUploadedBy(User $user): bool;

    public function uploaderName(): string;

    /** Masquée après une demande de retrait, en attendant la décision de la présidence */
    public function isHidden(): bool;

    /** Visible de tous les membres : prête et pas masquée */
    public function isVisible(): bool;

    /** Demande de retrait d'un membre : masquée tout de suite, la présidence est prévenue. */
    public function hideOnRequest(User $member, \DateTimeImmutable $now): void;

    /** La présidence la remet en ligne. */
    public function restore(): void;
}
