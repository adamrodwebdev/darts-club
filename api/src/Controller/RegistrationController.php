<?php

namespace App\Controller;

use App\Dto\EmailRequest;
use App\Dto\Registration;
use App\Message\SendVerificationEmail;
use App\Preference\Language;
use App\Repository\UserRepository;
use App\Security\ClientNetwork;
use App\Security\RequestThrottle;
use App\Service\UserRegistrar;
use App\ValueObject\EmailAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Route('/api/auth', name: 'auth_')]
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'], format: 'json')]
    public function register(
        #[MapRequestPayload(acceptFormat: 'json')] Registration $registration,
        UserRegistrar $registrar,
        Request $request,
        RateLimiterFactoryInterface $registrationLimiter,
    ): JsonResponse {
        $this->requestThrottle->consume($registrationLimiter, (string) ClientNetwork::of($request));

        $registrar->register($registration, Language::fromLocale($request->getLocale()));

        return $this->accepted();
    }

    /** Lien signé reçu par email. */
    #[Route('/verify', name: 'verify', methods: ['GET'])]
    public function verify(
        Request $request,
        UserRepository $users,
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $user = $users->find($request->query->getString('id'));
        if (null === $user) {
            return $this->toFront('invalid');
        }

        if (!$user->isVerified()) {
            try {
                $verifyEmailHelper->validateEmailConfirmationFromRequest($request, $user->getId()->toRfc4122(), $user->getEmail());
            } catch (VerifyEmailExceptionInterface) {
                return $this->toFront('invalid');
            }

            $user->markVerified();
            $em->flush();
        }

        return $this->toFront('success');
    }

    #[Route('/verify/resend', name: 'verify_resend', methods: ['POST'], format: 'json')]
    public function resend(
        #[MapRequestPayload(acceptFormat: 'json')] EmailRequest $payload,
        UserRepository $users,
        MessageBusInterface $bus,
        Request $request,
        RateLimiterFactoryInterface $verificationResendLimiter,
    ): JsonResponse {
        $email = EmailAddress::fromString($payload->email);
        // Par destinataire, le plafond est celui de ThrottledMailer (4 par jour)
        $this->requestThrottle->consume($verificationResendLimiter, (string) ClientNetwork::of($request));

        $user = $users->findOneByEmail($email);
        if (null !== $user && !$user->isVerified()) {
            $bus->dispatch(new SendVerificationEmail($user->getId()->toRfc4122()));
        }

        return $this->accepted();
    }

    /** Même réponse que le compte existe ou non. */
    private function accepted(): JsonResponse
    {
        return $this->json(
            ['message' => 'Si l\'adresse est valide, un email vient d\'être envoyé.'],
            Response::HTTP_ACCEPTED,
        );
    }

    private function toFront(string $status): RedirectResponse
    {
        return new RedirectResponse(rtrim($this->frontendUrl, '/').'/connexion?verify='.$status);
    }
}
