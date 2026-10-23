<?php

namespace App\Entity;

use App\ClubEvent\Attendance;
use App\ClubEvent\GuestRequest;
use App\Preference\Language;
use App\Repository\GuestResponseRepository;
use App\ValueObject\DisplayName;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Réponse d'une personne sans compte à un événement public. Elle ne compte
 * qu'une fois confirmée par le lien reçu par email ; une nouvelle demande
 * émet un nouveau lien (l'ancien ne vaut plus) et attend sa confirmation.
 * Le jeton n'est conservé que sous forme d'empreinte. Géré par ClubEvent.
 */
#[ORM\Entity(repositoryClass: GuestResponseRepository::class)]
#[ORM\Table(name: 'event_guest_response')]
#[ORM\UniqueConstraint(name: 'uniq_guest_response_event_email', columns: ['event_id', 'email'])]
class GuestResponse
{
    /** Délai pour confirmer une demande */
    public const CONFIRMATION_DELAY = 'PT48H';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: DisplayName::MAX)]
    private string $name;

    #[ORM\Column(length: 180)]
    private string $email;

    /** Réponse confirmée (null tant que la première demande n'est pas confirmée) */
    #[ORM\Column(length: 10, nullable: true, enumType: Attendance::class)]
    private ?Attendance $answer = null;

    /** Réponse demandée, en attente de confirmation */
    #[ORM\Column(length: 10, nullable: true, enumType: Attendance::class)]
    private ?Attendance $pendingAnswer;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $tokenIssuedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(length: 2, enumType: Language::class)]
    private Language $language;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'guestResponses')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClubEvent $event,
        GuestRequest $request,
        string $tokenHash,
        \DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->name = $request->name->value;
        $this->email = $request->email->value;
        $this->pendingAnswer = $request->answer;
        $this->tokenHash = $tokenHash;
        $this->tokenIssuedAt = $now;
        $this->language = $request->language;
        $this->createdAt = $now;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Nouvelle demande pour la même adresse : nouveau lien, nouvelle réponse à confirmer. */
    public function reissue(GuestRequest $request, string $tokenHash, \DateTimeImmutable $now): void
    {
        $this->name = $request->name->value;
        $this->pendingAnswer = $request->answer;
        $this->tokenHash = $tokenHash;
        $this->tokenIssuedAt = $now;
        $this->language = $request->language;
    }

    /** Applique la réponse en attente ; appelé par ClubEvent (contrôle des places). */
    public function confirm(\DateTimeImmutable $now): void
    {
        if (null === $this->pendingAnswer) {
            return;
        }
        if ($this->isConfirmationExpired($now)) {
            throw new \InvalidArgumentException('Ce lien de confirmation a expiré : renvoie ta réponse depuis la page de l\'événement.');
        }

        $this->answer = $this->pendingAnswer;
        $this->pendingAnswer = null;
        $this->confirmedAt ??= $now;
    }

    /** Changement d'avis depuis le lien personnel ; appelé par ClubEvent. */
    public function changeAnswer(Attendance $answer): void
    {
        if (!$this->isConfirmed()) {
            throw new \InvalidArgumentException('Confirme d\'abord ta réponse.');
        }

        $this->answer = $answer;
        $this->pendingAnswer = null;
    }

    /** Comparaison à temps constant (le jeton n'est connu que par son empreinte) */
    public function matchesToken(string $token): bool
    {
        return hash_equals($this->tokenHash, self::hashToken($token));
    }

    public function isConfirmed(): bool
    {
        return null !== $this->answer;
    }

    public function counts(Attendance $answer): bool
    {
        return $answer === $this->answer;
    }

    public function isConfirmationExpired(\DateTimeImmutable $now): bool
    {
        return null !== $this->pendingAnswer && $now > $this->tokenIssuedAt->add(new \DateInterval(self::CONFIRMATION_DELAY));
    }

    public function isFor(EmailAddress $email): bool
    {
        return $this->email === $email->value;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEvent(): ClubEvent
    {
        return $this->event;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function answer(): ?Attendance
    {
        return $this->answer;
    }

    public function pendingAnswer(): ?Attendance
    {
        return $this->pendingAnswer;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function language(): Language
    {
        return $this->language;
    }
}
