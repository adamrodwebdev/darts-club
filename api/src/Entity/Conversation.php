<?php

namespace App\Entity;

use App\Event\DomainEventsTrait;
use App\Event\RecordsDomainEvents;
use App\Messaging\Mailbox;
use App\Messaging\MailboxTopic;
use App\Messaging\MessageBody;
use App\Messaging\MessagePosted;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Fil de discussion : entre deux adhérents, ou entre un adhérent et une
 * boîte du bureau (accessible à tout titulaire du rôle correspondant).
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Index(name: 'idx_conversation_requester', columns: ['requester_id'])]
class Conversation implements RecordsDomainEvents
{
    use DomainEventsTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 20, nullable: true, enumType: Mailbox::class)]
    private ?Mailbox $mailbox = null;

    /**
     * Fil d'une boîte du bureau : l'adhérent qui a écrit. Lui et les titulaires actuels du rôle y ont
     * accès, pas les anciens : avoir lu ou répondu ne donne aucun droit une fois le rôle quitté.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $requester = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $subject = null;

    /** @var Collection<int, Participant> */
    #[ORM\OneToMany(targetEntity: Participant::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participants;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastMessageAt;

    private function __construct(\DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->participants = new ArrayCollection();
        $this->createdAt = $this->lastMessageAt = $now;
    }

    public static function between(User $a, User $b, \DateTimeImmutable $now): self
    {
        if ($a->getId()->equals($b->getId())) {
            throw new \InvalidArgumentException('Impossible de s\'écrire à soi-même.');
        }

        $conversation = new self($now);
        $conversation->join($a);
        $conversation->join($b);

        return $conversation;
    }

    public static function toMailbox(User $member, MailboxTopic $topic, \DateTimeImmutable $now): self
    {
        $conversation = new self($now);
        $conversation->mailbox = $topic->mailbox;
        $conversation->subject = $topic->subject;
        $conversation->requester = $member;
        $conversation->join($member);

        return $conversation;
    }

    /** Fil d'une boîte du bureau : l'adhérent qui l'a ouvert (null pour un fil direct, ou compte supprimé) */
    public function requester(): ?User
    {
        return $this->requester;
    }

    public function isOpenedBy(User $user): bool
    {
        return null !== $this->requester && $this->requester->getId()->equals($user->getId());
    }

    /** Publie un message ; renvoie le message créé, à persister (fabrique). */
    public function post(User $author, MessageBody $body, \DateTimeImmutable $now): Message
    {
        $message = new Message(conversation: $this, author: $author, body: $body, now: $now);
        $this->lastMessageAt = $now;
        // Un titulaire du rôle qui répond devient participant du fil
        $this->join($author)->markRead($this->lastMessageAt);
        $this->recordThat(new MessagePosted($this->id, $message->getId(), $author->getId()));

        return $message;
    }

    public function markReadBy(User $user, \DateTimeImmutable $now): void
    {
        $this->join($user)->markRead($now);
    }

    public function isParticipant(User $user): bool
    {
        return null !== $this->participantFor($user);
    }

    public function participantFor(User $user): ?Participant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser()->getId()->equals($user->getId())) {
                return $participant;
            }
        }

        return null;
    }

    /** @return list<User> */
    public function participantUsers(): array
    {
        return array_values(array_map(static fn (Participant $p): User => $p->getUser(), $this->participants->toArray()));
    }

    /** L'autre adhérent d'une conversation directe. */
    public function counterpartOf(User $user): ?User
    {
        if (null !== $this->mailbox) {
            return null;
        }

        foreach ($this->participantUsers() as $participant) {
            if (!$participant->getId()->equals($user->getId())) {
                return $participant;
            }
        }

        return null;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMailbox(): ?Mailbox
    {
        return $this->mailbox;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getLastMessageAt(): \DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function join(User $user): Participant
    {
        $participant = $this->participantFor($user);
        if (null === $participant) {
            $participant = new Participant($this, $user);
            $this->participants->add($participant);
        }

        return $participant;
    }
}
