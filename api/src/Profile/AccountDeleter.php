<?php

namespace App\Profile;

use App\Entity\Notification;
use App\Entity\OAuthIdentity;
use App\Entity\Participant;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Droit à l'effacement (RGPD art. 17) : supprime le compte et ses données. */
final class AccountDeleter
{
    /** Entités rattachées à l'utilisateur, supprimées explicitement (indépendant des FK du SGBD) */
    private const OWNED = [
        Notification::class => 'recipient',
        PushSubscription::class => 'user',
        OAuthIdentity::class => 'user',
        Participant::class => 'user',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvatarStorage $avatars,
        private readonly MessageRepository $messages,
    ) {
    }

    public function delete(User $user): void
    {
        $avatar = $user->changeAvatar(null);

        $this->em->wrapInTransaction(function () use ($user): void {
            // Messages conservés pour les autres participants, auteur anonymisé
            $this->messages->anonymizeAuthor($user);

            foreach (self::OWNED as $class => $field) {
                $this->em->createQueryBuilder()
                    ->delete($class, 'e')
                    ->where("e.{$field} = :user")
                    ->setParameter('user', $user->getId(), 'uuid')
                    ->getQuery()
                    ->execute();
            }

            $this->em->remove($user);
        });

        if (null !== $avatar) {
            $this->avatars->delete($avatar);
        }
    }
}
