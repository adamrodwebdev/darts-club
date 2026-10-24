<?php

namespace App\Security\Passkey;

use App\Event\PasskeyAdded;
use App\Event\PasskeysReset;
use App\Message\SendPasskeyNotice;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/** Email au titulaire du compte : clé d'accès ajoutée, ou clés retirées par l'administrateur. */
final class PasskeyNotices
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[AsEventListener]
    public function onAdded(PasskeyAdded $event): void
    {
        $this->bus->dispatch(new SendPasskeyNotice($event->userId->toRfc4122(), SendPasskeyNotice::ADDED));
    }

    #[AsEventListener]
    public function onReset(PasskeysReset $event): void
    {
        $this->bus->dispatch(new SendPasskeyNotice($event->userId->toRfc4122(), SendPasskeyNotice::RESET));
    }
}
