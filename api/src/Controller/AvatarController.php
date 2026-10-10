<?php

namespace App\Controller;

use App\Entity\User;
use App\Media\ImageStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Avatars visibles des seuls adhérents connectés. */
#[IsGranted('ROLE_MEMBER')]
final class AvatarController extends AbstractController
{
    #[Route('/api/users/{id}/avatar', name: 'user_avatar', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'])]
    public function __invoke(User $user, ImageStorage $avatarStorage, Request $request): BinaryFileResponse
    {
        $avatar = $user->avatar();
        $path = null !== $avatar ? $avatarStorage->path($avatar) : null;
        if (null === $avatar || null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->setEtag($avatar);
        $response->isNotModified($request);

        return $response;
    }
}
