<?php

namespace App\ClubEvent;

use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Entity\ClubEvent;
use App\Entity\EventPost;
use App\Entity\User;
use App\Messaging\MessageBody;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Fil de l'archive : posts des adhérents une fois l'événement commencé. */
final class EventPosts
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly AuditTrail $audit,
    ) {
    }

    public function add(ClubEvent $event, User $author, string $text): EventPost
    {
        $now = $this->clock->now();
        $event->assertArchiveOpen($now);

        try {
            $post = new EventPost(event: $event, author: $author, body: MessageBody::fromString($text), now: $now);
        } catch (\InvalidArgumentException $e) {
            throw new RegistrationException($e->getMessage(), $e);
        }

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    /** Retiré par un organisateur plutôt que par son auteur : modération, inscrite au journal */
    public function delete(EventPost $post, User $actor): void
    {
        if (!$post->isWrittenBy($actor)) {
            $this->audit->record($actor, new AuditEntry('post.delete', $post->getEvent()->getTitle(), [
                'author' => $post->getAuthor()?->displayName()->value ?? '',
            ]));
        }
        $this->em->remove($post);
        $this->em->flush();
    }
}
