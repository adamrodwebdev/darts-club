<?php

namespace App\EventListener;

use App\Event\ProviderLinked;
use App\Message\SendProviderLinkedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/** Un fournisseur de connexion relié à un compte existant : son titulaire est prévenu par email. */
#[AsEventListener]
final class ProviderLinkedListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ProviderLinked $event): void
    {
        $this->bus->dispatch(new SendProviderLinkedEmail($event->userId->toRfc4122(), $event->provider));
    }
}
