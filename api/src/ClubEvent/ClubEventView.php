<?php

namespace App\ClubEvent;

use App\Entity\ClubEvent;
use App\Entity\EventPhoto;
use App\Entity\EventPost;
use App\Entity\User;
use App\Messaging\MessagingView;

/** Représentations JSON du calendrier et des archives. */
final class ClubEventView
{
    public function __construct(
        private readonly MessagingView $members,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(ClubEvent $event, User $viewer, \DateTimeImmutable $now): array
    {
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
            'registered' => $event->registrationCount(),
            'isRegistered' => $event->isRegistered($viewer),
            'full' => $event->isFull(),
            'started' => $event->hasStarted($now),
            'past' => $event->isPast($now),
            'hasSummary' => null !== $event->getSummary(),
        ];
    }

    /**
     * @param list<EventPhoto> $photos
     * @param list<EventPost>  $posts
     *
     * @return array<string, mixed>
     */
    public function detail(ClubEvent $event, User $viewer, \DateTimeImmutable $now, array $photos, array $posts, bool $canManage): array
    {
        return $this->summary($event, $viewer, $now) + [
            'description' => $event->getDescription(),
            'organizer' => $event->getOrganizerName(),
            'summary' => $event->getSummary(),
            'canManage' => $canManage,
            'photos' => array_map(static fn (EventPhoto $p): array => $p->toArray() + ['mine' => $p->isUploadedBy($viewer)], $photos),
            'posts' => array_map(fn (EventPost $p): array => [
                'id' => $p->getId()->toRfc4122(),
                'body' => $p->getBody(),
                'at' => $p->getCreatedAt()->format(\DATE_ATOM),
                'mine' => $p->isWrittenBy($viewer),
                'author' => null !== $p->getAuthor()
                    ? $this->members->member($p->getAuthor())
                    : ['id' => null, 'displayName' => 'Ancien adhérent', 'avatarUrl' => null, 'badges' => []],
            ], $posts),
            'participants' => $canManage
                ? array_map(fn (User $u): array => $this->members->member($u), $event->participants())
                : null,
        ];
    }
}
