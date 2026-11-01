<?php

namespace App\Gallery;

use App\Entity\User;
use App\Event\MediaRemovalRequested;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de retrait d'une photo ou d'une vidéo : masquée dès la demande (droit à l'image),
 * la présidence la remet en ligne ou la supprime. Pour les entités de galerie qui enregistrent
 * leurs événements de domaine.
 */
trait HiddenOnRequest
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $hiddenAt = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $hiddenBy = null;

    public function hideOnRequest(User $member, \DateTimeImmutable $now): void
    {
        if (null !== $this->hiddenAt) {
            return;
        }

        $this->hiddenAt = $now;
        $this->hiddenBy = mb_substr($member->displayName()->value, 0, 80);
        $this->recordThat(new MediaRemovalRequested($this->getEvent()->getId(), $this->kind(), $this->hiddenBy));
    }

    public function restore(): void
    {
        $this->hiddenAt = null;
        $this->hiddenBy = null;
    }

    public function isHidden(): bool
    {
        return null !== $this->hiddenAt;
    }

    /** Nom du membre qui a demandé le retrait, montré à la présidence */
    public function hiddenBy(): ?string
    {
        return $this->hiddenBy;
    }
}
