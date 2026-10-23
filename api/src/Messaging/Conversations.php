<?php

namespace App\Messaging;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Cas d'usage de la messagerie ; MessagePosted est publié après le flush. */
final class Conversations
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Réutilise le fil existant entre deux adhérents. */
    public function writeTo(User $author, User $recipient, MessageBody $body): Conversation
    {
        $conversation = $this->conversations->findDirectBetween($author, $recipient)
            ?? Conversation::between($author, $recipient, $this->clock->now());

        return $this->post($conversation, $author, $body);
    }

    public function writeToMailbox(User $author, MailboxTopic $topic, MessageBody $body): Conversation
    {
        return $this->post(Conversation::toMailbox($author, $topic, $this->clock->now()), $author, $body);
    }

    public function reply(Conversation $conversation, User $author, MessageBody $body): Conversation
    {
        return $this->post($conversation, $author, $body);
    }

    private function post(Conversation $conversation, User $author, MessageBody $body): Conversation
    {
        $this->em->persist($conversation);
        $this->em->persist($conversation->post($author, $body, $this->clock->now()));
        $this->em->flush();

        return $conversation;
    }
}
