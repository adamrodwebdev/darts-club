<?php

namespace App\Profile;

use App\Entity\EventPhoto;
use App\Entity\EventPost;
use App\Entity\Notification;
use App\Entity\OAuthIdentity;
use App\Entity\Participant;
use App\Entity\Payment;
use App\Entity\PushSubscription;
use App\Entity\Registration;
use App\Entity\User;
use App\Media\ImageStorage;
use App\Repository\MembershipRepository;
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
        Registration::class => 'user',
    ];

    /** Contenus conservés pour les autres, détachés du compte (auteur anonymisé) */
    private const ANONYMIZED = [
        Payment::class => 'recordedBy',
        EventPhoto::class => 'uploader',
        EventPost::class => 'author',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageStorage $avatarStorage,
        private readonly MessageRepository $messages,
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function delete(User $user): void
    {
        $avatar = $user->avatar();
        $user->changeAvatar(null);

        $this->em->wrapInTransaction(function () use ($user): void {
            // Messages conservés pour les autres participants, auteur anonymisé
            $this->messages->anonymizeAuthor($user);
            // Obligation comptable : la cotisation reste, détachée du compte
            $this->memberships->detachUser($user);

            foreach (self::ANONYMIZED as $class => $field) {
                $this->em->createQueryBuilder()
                    ->update($class, 'e')
                    ->set("e.{$field}", 'NULL')
                    ->where("e.{$field} = :user")
                    ->setParameter('user', $user->getId(), 'uuid')
                    ->getQuery()
                    ->execute();
            }

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
            $this->avatarStorage->delete($avatar);
        }
    }
}
