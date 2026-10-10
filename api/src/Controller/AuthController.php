<?php

namespace App\Controller;

use App\Entity\User;
use App\Profile\ProfileView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth', name: 'auth_', format: 'json')]
final class AuthController extends AbstractController
{
    /** Atteint uniquement après authentification réussie par json_login. */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(#[CurrentUser] User $user): JsonResponse
    {
        return $this->me($user);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('ROLE_MEMBER')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(ProfileView::of($user));
    }

    /** Intercepté par le pare-feu (logout). */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }
}
