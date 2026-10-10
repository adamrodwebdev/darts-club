<?php

namespace App\EventListener;

use App\Event\UserRegistered;
use App\Message\SendVerificationEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class UserRegisteredListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(UserRegistered $event): void
    {
        if (!$event->viaOAuth) {
            $this->bus->dispatch(new SendVerificationEmail($event->userId->toRfc4122()));
        }
    }
}
