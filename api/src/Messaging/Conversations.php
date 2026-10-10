<?php

namespace App\Messaging;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Cas d'usage de la messagerie ; MessagePosted est publié après le flush. */
final class Conversations
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Réutilise le fil existant entre deux adhérents. */
    public function writeTo(User $author, User $recipient, MessageBody $body): Conversation
    {
        $conversation = $this->conversations->findDirectBetween($author, $recipient)
            ?? Conversation::between($author, $recipient);

        return $this->post($conversation, $author, $body);
    }

    public function writeToMailbox(User $author, Mailbox $mailbox, ?string $subject, MessageBody $body): Conversation
    {
        return $this->post(Conversation::toMailbox($author, $mailbox, $subject), $author, $body);
    }

    public function reply(Conversation $conversation, User $author, MessageBody $body): Conversation
    {
        return $this->post($conversation, $author, $body);
    }

    private function post(Conversation $conversation, User $author, MessageBody $body): Conversation
    {
        $this->em->persist($conversation);
        $this->em->persist($conversation->post($author, $body));
        $this->em->flush();

        return $conversation;
    }
}
