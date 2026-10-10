<?php

namespace App\Controller;

use App\Entity\User;
use App\Notification\Category;
use App\Notification\Channel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/notification-preferences', name: 'notification_preferences_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class NotificationPreferencesController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['preferences' => $user->notificationPreferences()->toArray()]);
    }

    /** Corps : {"preferences": {"evenement": ["email", "push"], "message": []}} */
    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $input = $request->toArray()['preferences'] ?? null;
        if (!\is_array($input)) {
            throw new UnprocessableEntityHttpException('Champ "preferences" attendu.');
        }

        $preferences = $user->notificationPreferences();
        foreach ($input as $categoryValue => $channelValues) {
            $category = Category::tryFrom((string) $categoryValue);
            if (null === $category || !\is_array($channelValues)) {
                throw new UnprocessableEntityHttpException(\sprintf('Catégorie inconnue : "%s".', $categoryValue));
            }

            $channels = [];
            foreach ($channelValues as $value) {
                $channel = \is_string($value) ? Channel::tryFrom($value) : null;
                if (null === $channel || !\in_array($channel, Channel::optional(), true)) {
                    throw new UnprocessableEntityHttpException('Canal inconnu ou non modifiable.');
                }
                $channels[] = $channel;
            }

            $preferences = $preferences->with($category, ...$channels);
        }

        $user->changeNotificationPreferences($preferences);
        $em->flush();

        return $this->show($user);
    }
}
