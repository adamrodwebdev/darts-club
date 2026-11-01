<?php

namespace App\Security\Voter;

use App\Entity\ClubEvent;
use App\Entity\EventVideo;
use App\Entity\User;
use App\Gallery\GalleryMedia;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Galerie des événements, réservée aux membres validés :
 * - la présidence (président et administrateur) publie photos et vidéos à tout moment ;
 * - les membres ajoutent des photos dès le début de l'événement ;
 * - une photo masquée (demande de retrait) n'est plus visible que de la présidence et de son auteur ;
 * - la présidence remet en ligne ce qui a été masqué.
 *
 * @extends Voter<string, mixed>
 */
final class EventGalleryVoter extends Voter
{
    public const ADD_PHOTOS = 'GALLERY_ADD_PHOTOS';
    public const ADD_VIDEOS = 'GALLERY_ADD_VIDEOS';
    /** Envoyer les morceaux d'une vidéo : son auteur seulement */
    public const SEND = 'GALLERY_SEND';
    public const VIEW = 'GALLERY_VIEW';
    public const REQUEST_REMOVAL = 'GALLERY_REQUEST_REMOVAL';
    public const MODERATE = 'GALLERY_MODERATE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly ClockInterface $clock,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::ADD_PHOTOS, self::ADD_VIDEOS => $subject instanceof ClubEvent,
            self::SEND => $subject instanceof EventVideo,
            self::VIEW, self::REQUEST_REMOVAL, self::MODERATE => $subject instanceof GalleryMedia,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$this->decisions->decide($token, ['ROLE_MEMBER'])) {
            return false;
        }

        $presidency = $this->decisions->decide($token, ['ROLE_PRESIDENT']);

        return match (true) {
            $subject instanceof ClubEvent => !$subject->isCancelled() && match ($attribute) {
                self::ADD_PHOTOS => $presidency || $subject->hasStarted($this->clock->now()),
                default => $presidency,
            },
            $subject instanceof EventVideo && self::SEND === $attribute => $subject->isUploadedBy($user),
            $subject instanceof GalleryMedia => match ($attribute) {
                self::VIEW => $subject->isVisible() || $presidency || $subject->isUploadedBy($user),
                self::REQUEST_REMOVAL => !$subject->isHidden() && !$subject->isUploadedBy($user),
                default => $presidency,
            },
            default => false,
        };
    }
}
