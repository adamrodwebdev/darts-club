<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\EventPost;
use App\Entity\GuestResponse;
use App\Entity\Registration;
use App\Entity\User;
use App\Messaging\MessagingView;
use App\Repository\EventPhotoRepository;
use App\Repository\EventPostRepository;
use Symfony\Component\Clock\ClockInterface;

/** Représentations JSON du calendrier et des archives. */
final class ClubEventView
{
    public function __construct(
        private readonly MessagingView $members,
        private readonly ClockInterface $clock,
        private readonly EventPhotoRepository $photos,
        private readonly EventPostRepository $posts,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(ClubEvent $event, User $viewer): array
    {
        $now = $this->clock->now();

        return [
            'id' => $event->getId()->toRfc4122(),
            'title' => $event->getTitle(),
            'location' => $event->getLocation(),
            'startsAt' => $event->getStartsAt()->format(\DATE_ATOM),
            'endsAt' => $event->getEndsAt()->format(\DATE_ATOM),
            'season' => $event->season()->label(),
            'status' => $event->getStatus()->value,
            'cancelReason' => $event->getCancelReason(),
            'capacity' => $event->getCapacity(),
            'registered' => $event->goingCount(),
            'maybe' => $event->maybeCount(),
            'isRegistered' => $event->isRegistered($viewer),
            'myAnswer' => $event->answerOf($viewer)?->value,
            'public' => $event->isPublic(),
            'city' => $event->getCity(),
            'publicPath' => $event->isPublic() ? '/e/'.$event->getSlug() : null,
            'full' => $event->isFull(),
            'started' => $event->hasStarted($now),
            'past' => $event->isPast($now),
            'hasSummary' => null !== $event->getSummary(),
            'poster' => $event->poster()?->toArray($event->getId()->toRfc4122()),
        ];
    }

    /**
     * Fiche complète, avec les archives (photos, souvenirs).
     *
     * @param bool $canManage organisateur : participants et invités confirmés en plus
     *
     * @return array<string, mixed>
     */
    public function detail(ClubEvent $event, User $viewer, bool $canManage): array
    {
        return $this->summary($event, $viewer) + [
            'description' => $event->getDescription(),
            'organizer' => $event->getOrganizerName(),
            'summary' => $event->getSummary(),
            'canManage' => $canManage,
            'photos' => array_map(static fn (EventPhoto $p): array => $p->toArray() + ['mine' => $p->isUploadedBy($viewer)], $this->photos->forEvent($event)),
            'posts' => array_map(fn (EventPost $p): array => [
                'id' => $p->getId()->toRfc4122(),
                'body' => $p->getBody(),
                'at' => $p->getCreatedAt()->format(\DATE_ATOM),
                'mine' => $p->isWrittenBy($viewer),
                'author' => null !== $p->getAuthor()
                    ? $this->members->member($p->getAuthor())
                    : ['id' => null, 'displayName' => 'Ancien adhérent', 'avatarUrl' => null, 'badges' => []],
            ], $this->posts->forEvent($event)),
            // Organisateurs : qui vient, qui hésite (adhérents puis invités confirmés)
            'participants' => $canManage
                ? array_map(fn (Registration $r): array => $this->members->member($r->getUser()) + ['answer' => $r->answer()->value], $event->registrations())
                : null,
            'guests' => $canManage
                ? array_map(static fn (GuestResponse $g): array => ['id' => $g->getId()->toRfc4122(), 'name' => $g->name(), 'email' => $g->email(), 'answer' => $g->answer()?->value, 'at' => $g->createdAt()->format(\DATE_ATOM)], $event->confirmedGuests())
                : null,
        ];
    }
}
