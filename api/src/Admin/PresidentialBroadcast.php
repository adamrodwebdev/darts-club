<?php

namespace App\Admin;

use App\Entity\Broadcast;
use App\Entity\User;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Message labellisé du président vers un adhérent, une sélection ou tous.
 * Livraison par le système de notifications (centre in-app, email, push).
 */
final class PresidentialBroadcast
{
    public function __construct(
        private readonly MemberNotifier $notifier,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly AuditTrail $audit,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Envoie le message ; renvoie la trace de l'envoi (fabrique, déjà enregistrée) */
    public function send(User $president, BroadcastDraft $draft, BroadcastAudience $audience): Broadcast
    {
        try {
            $content = new NotificationContent(category: $draft->label->category(), title: $draft->title, body: $draft->body, link: '/notifications');
        } catch (\InvalidArgumentException $e) {
            throw new AdministrationException($e->getMessage(), $e);
        }

        if (null === $audience->userIds) {
            $recipients = Recipients::allMembers();
            $count = \count($this->users->findActiveIds());
        } else {
            $ids = $this->activeIdsAmong($audience->userIds);
            if ([] === $ids) {
                throw new AdministrationException('Sélectionne au moins un adhérent actif.');
            }
            $recipients = Recipients::users(...$ids);
            $count = \count($ids);
        }

        $broadcast = new Broadcast(
            label: $draft->label,
            title: $content->title,
            body: $content->body,
            authorName: mb_substr($president->displayName()->value, 0, 80),
            toAllMembers: $audience->isEveryone(),
            recipientCount: $count,
            now: $this->clock->now(),
        );
        $this->em->persist($broadcast);
        $this->audit->record($president, new AuditEntry('broadcast.send', $content->labelledTitle(), ['recipients' => $count]));
        $this->em->flush();

        $this->notifier->notify($content, $recipients);

        return $broadcast;
    }

    /**
     * @param list<string> $userIds
     *
     * @return list<Uuid>
     */
    private function activeIdsAmong(array $userIds): array
    {
        $active = array_flip($this->users->findActiveIds());
        $ids = [];
        foreach (array_unique($userIds) as $id) {
            if (isset($active[$id])) {
                $ids[] = Uuid::fromString($id);
            }
        }

        return $ids;
    }
}
