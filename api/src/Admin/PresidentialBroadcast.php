<?php

namespace App\Admin;

use App\Entity\Broadcast;
use App\Entity\User;
use App\Notification\MemberNotifier;
use App\Notification\NotificationContent;
use App\Notification\Recipients;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
    }

    /** @param list<string>|null $userIds null = tous les adhérents */
    public function send(User $president, BroadcastLabel $label, string $title, string $body, ?array $userIds): Broadcast
    {
        try {
            $content = new NotificationContent($label->category(), $title, $body, '/notifications');
        } catch (\InvalidArgumentException $e) {
            throw new AdministrationException($e->getMessage(), $e);
        }

        if (null === $userIds) {
            $recipients = Recipients::allMembers();
            $count = \count($this->users->findActiveIds());
        } else {
            $ids = $this->activeIdsAmong($userIds);
            if ([] === $ids) {
                throw new AdministrationException('Sélectionne au moins un adhérent actif.');
            }
            $recipients = Recipients::users(...$ids);
            $count = \count($ids);
        }

        $broadcast = new Broadcast($label, $content->title, $content->body, mb_substr($president->displayName()->value, 0, 80), null === $userIds, $count);
        $this->em->persist($broadcast);
        $this->audit->record($president, 'broadcast.send', $content->labelledTitle(), ['recipients' => $count]);
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
