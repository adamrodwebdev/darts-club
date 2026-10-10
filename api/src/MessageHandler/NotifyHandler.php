<?php

namespace App\MessageHandler;

use App\Message\DeliverNotification;
use App\Message\Notify;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class NotifyHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(Notify $message): void
    {
        $ids = $message->recipients->allMembers
            ? $this->users->findVerifiedIds()
            : $message->recipients->userIds;

        foreach ($ids as $id) {
            $this->bus->dispatch(new DeliverNotification($id, $message->content));
        }
    }
}
