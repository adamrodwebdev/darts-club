<?php

namespace App\Controller;

use App\Dto\EmailRequest;
use App\Dto\PasswordConfirmation;
use App\Dto\Registration;
use App\Message\SendVerificationEmail;
use App\Preference\Language;
use App\Repository\UserRepository;
use App\Security\ClientNetwork;
use App\Security\EmailConfirmation;
use App\Security\RequestThrottle;
use App\Service\UserRegistrar;
use App\Translation\Unprocessable;
use App\ValueObject\EmailAddress;
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
use Symfony\Component\Uid\Uuid;

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

    /**
     * Lien reçu par email (cliqué tel quel) : renvoie vers la page de confirmation, qui demande le
     * mot de passe choisi à l'inscription (EmailConfirmation). Les paramètres signés suivent.
     */
    #[Route('/verify', name: 'verify', methods: ['GET'])]
    public function verify(Request $request): RedirectResponse
    {
        return new RedirectResponse(rtrim($this->frontendUrl, '/').'/confirmer-email?'.$request->getQueryString());
    }

    /** Paramètres signés du lien dans l'adresse, mot de passe dans le corps ; renvoie l'email du compte (connexion). */
    #[Route('/verify', name: 'verify_confirm', methods: ['POST'], format: 'json')]
    public function confirm(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json')] PasswordConfirmation $input,
        UserRepository $users,
        EmailConfirmation $confirmation,
        RateLimiterFactoryInterface $verificationConfirmLimiter,
    ): JsonResponse {
        // Le mot de passe se devine ici aussi : essais limités par origine et par compte
        $this->requestThrottle->consume($verificationConfirmLimiter, (string) ClientNetwork::of($request));
        $id = $request->query->getString('id');
        $user = Uuid::isValid($id) ? $users->find($id) : null;
        if (null === $user) {
            throw new Unprocessable('Lien invalide ou expiré : demande un nouveau lien.');
        }
        $this->requestThrottle->consume($verificationConfirmLimiter, $user->getId()->toRfc4122());

        $confirmation->confirm($user, $request, $input->password);

        return $this->json(['email' => $user->getEmail()]);
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
}
