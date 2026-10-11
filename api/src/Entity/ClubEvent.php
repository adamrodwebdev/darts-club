<?php

namespace App\Entity;

use App\ClubEvent\Attendance;
use App\ClubEvent\ClubEventCancelled;
use App\ClubEvent\ClubEventRescheduled;
use App\ClubEvent\ClubEventScheduled;
use App\ClubEvent\EventDetails;
use App\ClubEvent\EventStatus;
use App\ClubEvent\Poster;
use App\ClubEvent\RegistrationException;
use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Membership\Season;
use App\Preference\Language;
use App\Repository\ClubEventRepository;
use App\Translation\InvalidValue;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\String\Slugger\AsciiSlugger;
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

    /** Affiche (voir Poster) : les quatre colonnes sont renseignées ensemble ou vides */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $posterLargeId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $posterCardId = null;

    #[ORM\Column(nullable: true)]
    private ?int $posterWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $posterHeight = null;

    /** Page publique ouverte aux personnes sans compte */
    #[ORM\Column(options: ['default' => false])]
    private bool $public = false;

    /** Ville montrée au public */
    #[ORM\Column(length: EventDetails::CITY_MAX, nullable: true)]
    private ?string $city = null;

    /** Adresse de la page publique (/e/{slug}), fixée à la première ouverture au public */
    #[ORM\Column(length: 140, unique: true, nullable: true)]
    private ?string $slug = null;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'event', cascade: ['persist'], orphanRemoval: true)]
    private Collection $registrations;

    /** @var Collection<int, GuestResponse> */
    #[ORM\OneToMany(targetEntity: GuestResponse::class, mappedBy: 'event', cascade: ['persist'], orphanRemoval: true)]
    private Collection $guestResponses;

    private function __construct(User $organizer, EventDetails $details, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->organizerName = mb_substr($organizer->displayName()->value, 0, 80);
        $this->createdAt = $now;
        $this->registrations = new ArrayCollection();
        $this->guestResponses = new ArrayCollection();
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
        if (null !== $details->capacity && $details->capacity < $this->goingCount()) {
            throw new InvalidValue('Impossible de descendre sous le nombre d\'inscrits actuel (%count%).', ['%count%' => $this->goingCount()]);
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

    /**
     * Réponse d'un adhérent (ou changement d'avis). « Pas sûr » ne prend pas de place.
     *
     * @return Registration|null la nouvelle inscription, null si une réponse existante a changé
     */
    public function respond(User $member, Attendance $answer, \DateTimeImmutable $now): ?Registration
    {
        $this->assertOpenForResponses($now);
        $existing = $this->registrationOf($member);
        $this->assertSeatFor($answer, $existing?->answer());

        if (null !== $existing) {
            $existing->changeAnswer($answer);

            return null;
        }

        $registration = new Registration($this, $member, $now, $answer);
        $this->registrations->add($registration);

        return $registration;
    }

    /** Raccourci : « je viens » */
    public function register(User $member, \DateTimeImmutable $now): ?Registration
    {
        return $this->respond($member, Attendance::Going, $now);
    }

    /**
     * Demande d'une personne sans compte (page publique). Rien ne compte avant confirmation.
     *
     * @param string $tokenHash empreinte du lien envoyé par email
     */
    public function requestGuestResponse(DisplayName $name, EmailAddress $email, Attendance $answer, string $tokenHash, Language $language, \DateTimeImmutable $now): GuestResponse
    {
        if (!$this->public) {
            throw new RegistrationException('Cet événement n\'est pas ouvert au public.');
        }
        $this->assertOpenForResponses($now);

        foreach ($this->guestResponses as $response) {
            if ($response->isFor($email)) {
                $response->reissue($name, $answer, $tokenHash, $language, $now);

                return $response;
            }
        }

        $response = new GuestResponse($this, $name, $email, $answer, $tokenHash, $language, $now);
        $this->guestResponses->add($response);

        return $response;
    }

    public function confirmGuestResponse(GuestResponse $response, \DateTimeImmutable $now): void
    {
        $this->assertOpenForResponses($now);
        if (null !== $response->pendingAnswer()) {
            $this->assertSeatFor($response->pendingAnswer(), $response->answer());
        }
        $response->confirm($now);
    }

    public function changeGuestAnswer(GuestResponse $response, Attendance $answer, \DateTimeImmutable $now): void
    {
        $this->assertOpenForResponses($now);
        $this->assertSeatFor($answer, $response->answer());
        $response->changeAnswer($answer);
    }

    public function removeGuestResponse(GuestResponse $response, \DateTimeImmutable $now): void
    {
        if ($this->hasStarted($now)) {
            throw new RegistrationException('L\'événement a commencé.');
        }
        $this->guestResponses->removeElement($response);
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

    /**
     * Pose (ou remplace) l'affiche.
     *
     * @return Poster|null l'affiche remplacée, dont les fichiers sont à supprimer
     */
    public function attachPoster(Poster $poster): ?Poster
    {
        $this->assertScheduled();
        $previous = $this->poster();
        $this->posterLargeId = $poster->largeId;
        $this->posterCardId = $poster->cardId;
        $this->posterWidth = $poster->width;
        $this->posterHeight = $poster->height;

        return $previous;
    }

    /** @return Poster|null l'affiche retirée, dont les fichiers sont à supprimer */
    public function removePoster(): ?Poster
    {
        $previous = $this->poster();
        $this->posterLargeId = $this->posterCardId = null;
        $this->posterWidth = $this->posterHeight = null;

        return $previous;
    }

    public function poster(): ?Poster
    {
        if (null === $this->posterLargeId || null === $this->posterCardId || null === $this->posterWidth || null === $this->posterHeight) {
            return null;
        }

        return new Poster($this->posterLargeId, $this->posterCardId, $this->posterWidth, $this->posterHeight);
    }

    public function writeSummary(string $summary): void
    {
        $summary = trim(str_replace("\r\n", "\n", $summary));
        if (mb_strlen($summary) > 10000) {
            throw new \InvalidArgumentException('Le résumé ne doit pas dépasser 10 000 caractères.');
        }

        $this->summary = '' !== $summary ? $summary : null;
    }

    public function answerOf(User $member): ?Attendance
    {
        return $this->registrationOf($member)?->answer();
    }

    /** Adhérent qui vient (« je viens ») */
    public function isRegistered(User $member): bool
    {
        return Attendance::Going === $this->answerOf($member);
    }

    /** Places occupées : adhérents et invités confirmés qui viennent */
    public function goingCount(): int
    {
        return $this->countAnswers(Attendance::Going);
    }

    public function maybeCount(): int
    {
        return $this->countAnswers(Attendance::Maybe);
    }

    public function isFull(): bool
    {
        return null !== $this->capacity && $this->goingCount() >= $this->capacity;
    }

    /** @return list<User> adhérents ayant répondu (« je viens » ou « pas sûr ») */
    public function participants(): array
    {
        return array_values(array_map(static fn (Registration $r): User => $r->getUser(), $this->registrations->toArray()));
    }

    /** @return list<Registration> */
    public function registrations(): array
    {
        return array_values($this->registrations->toArray());
    }

    /** @return list<GuestResponse> invités dont la réponse est confirmée */
    public function confirmedGuests(): array
    {
        return array_values(array_filter($this->guestResponses->toArray(), static fn (GuestResponse $r): bool => $r->isConfirmed()));
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
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
        $city = trim((string) $details->city);
        $this->city = '' !== $city ? mb_substr($city, 0, EventDetails::CITY_MAX) : null;
        $this->public = $details->public;
        // Adresse stable : un lien partagé reste valable même si le titre change
        if ($this->public && null === $this->slug) {
            $base = (new AsciiSlugger('fr'))->slug($this->title.' '.$this->startsAt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d'))->lower()->truncate(120, '', false)->trimEnd('-');
            $this->slug = $base.'-'.bin2hex(random_bytes(3));
        }
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

    private function registrationOf(User $member): ?Registration
    {
        foreach ($this->registrations as $registration) {
            if ($registration->isFor($member)) {
                return $registration;
            }
        }

        return null;
    }

    private function countAnswers(Attendance $answer): int
    {
        $members = \count(array_filter($this->registrations->toArray(), static fn (Registration $r): bool => $answer === $r->answer()));
        $guests = \count(array_filter($this->guestResponses->toArray(), static fn (GuestResponse $r): bool => $r->counts($answer)));

        return $members + $guests;
    }

    private function assertOpenForResponses(\DateTimeImmutable $now): void
    {
        if ($this->isCancelled()) {
            throw new RegistrationException('Cet événement est annulé.');
        }
        if ($this->hasStarted($now)) {
            throw new RegistrationException('Les inscriptions sont closes.');
        }
    }

    /** Passer à « je viens » demande une place libre */
    private function assertSeatFor(Attendance $answer, ?Attendance $current): void
    {
        if ($answer->takesASeat() && Attendance::Going !== $current && $this->isFull()) {
            throw new RegistrationException('Complet : plus de place disponible.');
        }
    }

    private function assertScheduled(): void
    {
        if ($this->isCancelled()) {
            throw new \LogicException('Événement annulé.');
        }
    }
}
