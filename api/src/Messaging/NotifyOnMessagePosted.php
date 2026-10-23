<?php

namespace App\Messaging;

use App\Entity\User;
use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Translation\TranslatableMessage;

/** Prévient les autres participants (et les titulaires de la boîte du bureau). */
#[AsEventListener]
final class NotifyOnMessagePosted
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages,
        private readonly UserRepository $users,
        private readonly MailboxAccess $mailboxAccess,
        private readonly MemberNotifier $notifier,
    ) {
    }

    public function __invoke(MessagePosted $event): void
    {
        $conversation = $this->conversations->find($event->conversationId);
        $message = $this->messages->find($event->messageId);
        $author = $this->users->find($event->authorId);
        if (null === $conversation || null === $message || null === $author) {
            return;
        }

        $mailbox = $conversation->getMailbox();
        $recipients = [];
        foreach ([...$conversation->participantUsers(), ...(null !== $mailbox ? $this->mailboxAccess->holdersOf($mailbox) : [])] as $user) {
            if (!$user->getId()->equals($author->getId())) {
                $recipients[$user->getId()->toRfc4122()] = $user->getId();
            }
        }
        if ([] === $recipients) {
            return;
        }

        $this->notifier->notify(
            new TranslatedNotification(
                category: Category::Message,
                title: self::title($author, $mailbox, $conversation->getSubject()),
                body: new TranslatableMessage('message.body', ['%preview%' => $message->body()->preview()], 'notifications'),
                link: '/messages/'.$conversation->getId()->toRfc4122(),
            ),
            Recipients::users(...array_values($recipients)),
        );
    }

    /** « Alice → Trésorerie : Cotisation », « Message de Bob » (tronqué à la livraison) */
    private static function title(User $author, ?Mailbox $mailbox, ?string $subject): TranslatableMessage
    {
        $key = (null !== $mailbox ? 'message.to_mailbox' : 'message.direct').(null !== $subject ? '_with_subject' : '');
        $parameters = ['%author%' => $author->displayName()->value, '%subject%' => (string) $subject];
        if (null !== $mailbox) {
            $parameters['%mailbox%'] = $mailbox;
        }

        return new TranslatableMessage($key, $parameters, 'notifications');
    }
}
