<?php

namespace App\Messaging;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Membership\MemberBadges;
use App\Profile\ProfileView;

/** Représentations JSON de la messagerie (aucun email exposé entre adhérents). */
final class MessagingView
{
    public function __construct(
        private readonly MemberBadges $badges,
    ) {
    }

    /** @return array{id: string, displayName: string, avatarUrl: ?string, badges: list<array{id: string, label: string}>} */
    public function member(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'displayName' => $user->displayName()->value,
            'avatarUrl' => ProfileView::avatarUrl($user),
            'badges' => $this->badges->present($user),
        ];
    }

    /** @return array<string, mixed> */
    public function conversation(Conversation $conversation, User $viewer, ConversationActivity $activity): array
    {
        $last = $activity->last;
        $mailbox = $conversation->getMailbox();
        $counterpart = $conversation->counterpartOf($viewer);

        return [
            'id' => $conversation->getId()->toRfc4122(),
            'mailbox' => null !== $mailbox ? ['id' => $mailbox->value, 'label' => $mailbox->label()] : null,
            'subject' => $conversation->getSubject(),
            'with' => null !== $counterpart ? $this->member($counterpart) : null,
            'lastMessage' => null !== $last ? ['preview' => $last->body()->preview(80), 'at' => $last->getCreatedAt()->format(\DATE_ATOM)] : null,
            'unread' => $activity->unread,
        ];
    }

    /** @return array<string, mixed> */
    public function message(Message $message, User $viewer): array
    {
        $author = $message->getAuthor();

        return [
            'id' => $message->getId()->toRfc4122(),
            'body' => $message->body()->value,
            'at' => $message->getCreatedAt()->format(\DATE_ATOM),
            'mine' => $message->isFrom($viewer),
            'author' => null !== $author
                ? $this->member($author)
                : ['id' => null, 'displayName' => 'Ancien adhérent', 'avatarUrl' => null, 'badges' => []],
        ];
    }
}
