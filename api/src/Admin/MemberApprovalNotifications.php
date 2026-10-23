<?php

namespace App\Admin;

use App\Event\MemberApproved;
use App\Event\MemberAwaitingApproval;
use App\Message\SendMemberApprovedEmail;
use App\Messaging\Mailbox;
use App\Messaging\MailboxAccess;
use App\Notification\Category;
use App\Notification\MemberNotifier;
use App\Notification\Recipients;
use App\Notification\TranslatedNotification;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Autour de la validation des nouveaux membres : la présidence (président et administrateur) apprend
 * qu'un compte attend, le membre apprend par email que l'espace membres lui est ouvert.
 */
final class MemberApprovalNotifications
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MailboxAccess $mailboxAccess,
        private readonly MemberNotifier $notifier,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[AsEventListener]
    public function onAwaitingApproval(MemberAwaitingApproval $event): void
    {
        $member = $this->users->find($event->userId);
        $approvers = array_map(static fn ($user) => $user->getId(), $this->mailboxAccess->holdersOf(Mailbox::President));
        if (null === $member || [] === $approvers) {
            return;
        }

        $this->notifier->notify(
            new TranslatedNotification(
                category: Category::Announcement,
                title: new TranslatableMessage('member.pending.title', ['%name%' => $member->displayName()->value], 'notifications'),
                body: new TranslatableMessage('member.pending.body', ['%email%' => $member->getEmail()], 'notifications'),
                link: '/admin',
            ),
            Recipients::users(...$approvers),
        );
    }

    #[AsEventListener]
    public function onApproved(MemberApproved $event): void
    {
        $this->bus->dispatch(new SendMemberApprovedEmail($event->userId->toRfc4122()));
    }
}
