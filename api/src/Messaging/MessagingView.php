<?php

namespace App\Messaging;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;

/** Représentations JSON de la messagerie (aucun email exposé entre adhérents). */
final class MessagingView
{
    /** @return array{id: string, displayName: string, avatarUrl: ?string} */
    public static function member(User $user): array
    {
        $id = $user->getId()->toRfc4122();

        return [
            'id' => $id,
            'displayName' => $user->displayName()->value,
            'avatarUrl' => null !== $user->avatar() ? '/api/users/'.$id.'/avatar?v='.substr((string) $user->avatar(), 0, 8) : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function conversation(Conversation $conversation, User $viewer, ?Message $last, int $unread): array
    {
        $mailbox = $conversation->getMailbox();
        $counterpart = $conversation->counterpartOf($viewer);

        return [
            'id' => $conversation->getId()->toRfc4122(),
            'mailbox' => null !== $mailbox ? ['id' => $mailbox->value, 'label' => $mailbox->label()] : null,
            'subject' => $conversation->getSubject(),
            'with' => null !== $counterpart ? self::member($counterpart) : null,
            'lastMessage' => null !== $last ? ['preview' => $last->body()->preview(80), 'at' => $last->getCreatedAt()->format(\DATE_ATOM)] : null,
            'unread' => $unread,
        ];
    }

    /** @return array<string, mixed> */
    public static function message(Message $message, User $viewer): array
    {
        $author = $message->getAuthor();

        return [
            'id' => $message->getId()->toRfc4122(),
            'body' => $message->body()->value,
            'at' => $message->getCreatedAt()->format(\DATE_ATOM),
            'mine' => $message->isFrom($viewer),
            'author' => null !== $author ? self::member($author) : ['id' => null, 'displayName' => 'Ancien adhérent', 'avatarUrl' => null],
        ];
    }
}
