<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\RequestThrottle;
use App\Security\TwoFactor\TwoFactorEnrollment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly TwoFactorEnrollment $enrollment,
        private readonly RateLimiterFactoryInterface $sensitiveActionLimiter,
    ) {
    }

    /** Page « formulaire » de scheb : la SPA y est renvoyée si la 2FA est attendue. */
    #[Route('/api/auth/2fa', name: 'auth_2fa', methods: ['GET'], format: 'json')]
    public function required(): JsonResponse
    {
        return $this->json(['twoFactorRequired' => true], Response::HTTP_UNAUTHORIZED);
    }

    /** Intercepté par le pare-feu (two_factor.check_path). */
    #[Route('/api/auth/2fa/check', name: 'auth_2fa_check', methods: ['POST'])]
    public function check(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }

    #[Route('/api/me/2fa/setup', name: 'two_factor_setup', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function setup(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->enrollment->start($user));
    }

    #[Route('/api/me/2fa/confirm', name: 'two_factor_confirm', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function confirm(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $this->throttle($user);

        return $this->json(['backupCodes' => $this->enrollment->confirm($user, self::code($request))]);
    }

    #[Route('/api/me/2fa', name: 'two_factor_disable', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function disable(#[CurrentUser] User $user, Request $request): Response
    {
        $this->throttle($user);
        $this->enrollment->disable($user, self::code($request));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private static function code(Request $request): string
    {
        $code = $request->toArray()['code'] ?? '';

        return \is_string($code) ? mb_substr($code, 0, 20) : '';
    }

    private function throttle(User $user): void
    {
        $this->requestThrottle->consume($this->sensitiveActionLimiter, $user->getId()->toRfc4122());
    }
}
