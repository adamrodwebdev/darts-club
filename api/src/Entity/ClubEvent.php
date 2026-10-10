<?php

namespace App\Entity;

use App\ClubEvent\ClubEventCancelled;
use App\ClubEvent\ClubEventRescheduled;
use App\ClubEvent\ClubEventScheduled;
use App\ClubEvent\EventDetails;
use App\ClubEvent\EventStatus;
use App\ClubEvent\RegistrationException;
use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Membership\Season;
use App\Repository\ClubEventRepository;
use App\Translation\InvalidValue;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Événement du club : planification, inscriptions, puis archive (résumé, photos, posts). */
#[ORM\Entity(repositoryClass: ClubEventRepository::class)]
#[ORM\Table(name: 'club_event')]
#[ORM\Index(name: 'idx_club_event_starts', columns: ['starts_at'])]
class ClubEvent implements RecordsDomainEvents
{
    use DomainEventsTrait;

    private const WEEK = 'P7D';
    private const DAY = 'P1D';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: EventDetails::TITLE_MAX)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: EventDetails::LOCATION_MAX)]
    private string $location;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(nullable: true)]
    private ?int $capacity;

    #[ORM\Column(length: 10, enumType: EventStatus::class)]
    private EventStatus $status = EventStatus::Scheduled;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 80)]
    private string $organizerName;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $weekReminderSentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dayReminderSentAt = null;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'event', cascade: ['persist'], orphanRemoval: true)]
    private Collection $registrations;

    private function __construct(User $organizer, EventDetails $details, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->organizerName = mb_substr($organizer->displayName()->value, 0, 80);
        $this->createdAt = $now;
        $this->registrations = new ArrayCollection();
        $this->apply($details);
    }

    public static function schedule(User $organizer, EventDetails $details, \DateTimeImmutable $now): self
    {
        if ($details->startsAt <= $now) {
            throw new \InvalidArgumentException('Un nouvel événement doit commencer dans le futur.');
        }

        $event = new self($organizer, $details, $now);
        $event->skipRemindersAlreadyCoveredBy($now);
        $event->recordThat(new ClubEventScheduled($event->id));

        return $event;
    }

    public function edit(EventDetails $details, \DateTimeImmutable $now): void
    {
        $this->assertScheduled();
        if (null !== $details->capacity && $details->capacity < $this->registrationCount()) {
            throw new InvalidValue('Impossible de descendre sous le nombre d\'inscrits actuel (%count%).', ['%count%' => $this->registrationCount()]);
        }

        $rescheduled = !$details->sameSchedule($this->startsAt, $this->endsAt);
        $this->apply($details);

        if ($rescheduled) {
            // Nouvelles dates : les rappels repartent de zéro
            $this->weekReminderSentAt = $this->dayReminderSentAt = null;
            $this->skipRemindersAlreadyCoveredBy($now);
            $this->recordThat(new ClubEventRescheduled($this->id));
        }
    }

    public function cancel(string $reason): void
    {
        $this->assertScheduled();
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \InvalidArgumentException('Le motif d\'annulation est obligatoire.');
        }

        $this->status = EventStatus::Cancelled;
        $this->cancelReason = mb_substr($reason, 0, 200);
        $this->recordThat(new ClubEventCancelled($this->id));
    }

    public function register(User $member, \DateTimeImmutable $now): Registration
    {
        if (EventStatus::Cancelled === $this->status) {
            throw new RegistrationException('Cet événement est annulé.');
        }
        if ($this->hasStarted($now)) {
            throw new RegistrationException('Les inscriptions sont closes.');
        }
        if ($this->isRegistered($member)) {
            throw new RegistrationException('Tu es déjà inscrit(e).');
        }
        if ($this->isFull()) {
            throw new RegistrationException('Complet : plus de place disponible.');
        }

        $registration = new Registration($this, $member, $now);
        $this->registrations->add($registration);

        return $registration;
    }

    public function unregister(User $member, \DateTimeImmutable $now): void
    {
        if ($this->hasStarted($now)) {
            throw new RegistrationException('L\'événement a commencé.');
        }

        foreach ($this->registrations as $registration) {
            if ($registration->isFor($member)) {
                $this->registrations->removeElement($registration);

                return;
            }
        }
    }

    public function writeSummary(string $summary): void
    {
        $summary = trim(str_replace("\r\n", "\n", $summary));
        if (mb_strlen($summary) > 10000) {
            throw new \InvalidArgumentException('Le résumé ne doit pas dépasser 10 000 caractères.');
        }

        $this->summary = '' !== $summary ? $summary : null;
    }

    public function isRegistered(User $member): bool
    {
        foreach ($this->registrations as $registration) {
            if ($registration->isFor($member)) {
                return true;
            }
        }

        return false;
    }

    public function registrationCount(): int
    {
        return $this->registrations->count();
    }

    public function isFull(): bool
    {
        return null !== $this->capacity && $this->registrationCount() >= $this->capacity;
    }

    /** @return list<User> */
    public function participants(): array
    {
        return array_values(array_map(static fn (Registration $r): User => $r->getUser(), $this->registrations->toArray()));
    }

    public function hasStarted(\DateTimeImmutable $now): bool
    {
        return $now >= $this->startsAt;
    }

    public function isPast(\DateTimeImmutable $now): bool
    {
        return $now > $this->endsAt;
    }

    public function isCancelled(): bool
    {
        return EventStatus::Cancelled === $this->status;
    }

    /** Rappel J-7 : à tous les adhérents. */
    public function isDueForWeekReminder(\DateTimeImmutable $now): bool
    {
        return !$this->isCancelled() && null === $this->weekReminderSentAt
            && !$this->hasStarted($now) && $now >= $this->startsAt->sub(new \DateInterval(self::WEEK));
    }

    /** Rappel J-1 : aux inscrits. */
    public function isDueForDayReminder(\DateTimeImmutable $now): bool
    {
        return !$this->isCancelled() && null === $this->dayReminderSentAt
            && !$this->hasStarted($now) && $now >= $this->startsAt->sub(new \DateInterval(self::DAY));
    }

    public function markWeekReminderSent(\DateTimeImmutable $now): void
    {
        $this->weekReminderSentAt = $now;
    }

    public function markDayReminderSent(\DateTimeImmutable $now): void
    {
        $this->dayReminderSentAt = $now;
    }

    public function season(): Season
    {
        return Season::containing($this->startsAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getOrganizerName(): string
    {
        return $this->organizerName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function apply(EventDetails $details): void
    {
        $this->title = trim($details->title);
        $this->description = trim(str_replace("\r\n", "\n", $details->description));
        $this->location = trim($details->location);
        // Stockage en UTC : même instant quel que soit le SGBD (SQLite ignore le décalage)
        $utc = new \DateTimeZone('UTC');
        $this->startsAt = $details->startsAt->setTimezone($utc);
        $this->endsAt = $details->endsAt->setTimezone($utc);
        $this->capacity = $details->capacity;
    }

    /** Planifié tardivement : la notification de création tient lieu de rappel. */
    private function skipRemindersAlreadyCoveredBy(\DateTimeImmutable $now): void
    {
        if ($this->isDueForWeekReminder($now)) {
            $this->weekReminderSentAt = $now;
        }
        if ($this->isDueForDayReminder($now)) {
            $this->dayReminderSentAt = $now;
        }
    }

    private function assertScheduled(): void
    {
        if ($this->isCancelled()) {
            throw new \LogicException('Événement annulé.');
        }
    }
}
