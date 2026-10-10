<?php

namespace App\EventListener;

use App\Event\DomainEvent;
use App\Event\RecordsDomainEvents;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Collecte les événements des entités écrites pendant le flush et ne les
 * publie qu'une fois la transaction validée : un listener ne réagit
 * jamais à un fait qui n'a pas été persisté.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DomainEventPublisher
{
    /** @var list<DomainEvent> */
    private array $pending = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entity) {
            if ($entity instanceof RecordsDomainEvents) {
                array_push($this->pending, ...$entity->releaseEvents());
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $events = $this->pending;
        $this->pending = [];

        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
