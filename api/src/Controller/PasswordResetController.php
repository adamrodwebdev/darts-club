<?php

namespace App\Controller;

use App\Dto\EmailRequest;
use App\Dto\NewPassword;
use App\Message\SendPasswordResetEmail;
use App\Repository\UserRepository;
use App\Security\ClientNetwork;
use App\Security\PasswordResets;
use App\Security\RequestThrottle;
use App\Security\ResetLink;
use App\ValueObject\EmailAddress;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** « Mot de passe oublié » : lien par email, puis nouveau mot de passe. */
#[Route('/api/auth/password', name: 'auth_password_', format: 'json')]
final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly RequestThrottle $requestThrottle,
    ) {
    }

    /** Même réponse que le compte existe ou non ; l'envoi est plafonné par adresse (ThrottledMailer). */
    #[Route('/forgot', name: 'forgot', methods: ['POST'])]
    public function forgot(
        #[MapRequestPayload(acceptFormat: 'json')] EmailRequest $payload,
        Request $request,
        UserRepository $users,
        MessageBusInterface $bus,
        RateLimiterFactoryInterface $passwordForgotLimiter,
    ): JsonResponse {
        $this->requestThrottle->consume($passwordForgotLimiter, (string) ClientNetwork::of($request));

        $user = $users->findOneByEmail(EmailAddress::fromString($payload->email));
        if (null !== $user) {
            $bus->dispatch(new SendPasswordResetEmail($user->getId()->toRfc4122()));
        }

        return $this->json(['message' => 'Si l\'adresse est valide, un email vient d\'être envoyé.'], Response::HTTP_ACCEPTED);
    }

    /** Paramètres du lien dans l'adresse (id, expires, token), nouveau mot de passe dans le corps. */
    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json')] NewPassword $input,
        PasswordResets $resets,
        RateLimiterFactoryInterface $passwordResetLimiter,
    ): Response {
        $this->requestThrottle->consume($passwordResetLimiter, (string) ClientNetwork::of($request));
        $resets->reset(ResetLink::fromQuery($request->query), $input->password);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
