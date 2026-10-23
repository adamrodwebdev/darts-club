<?php

namespace App\Controller;

use App\Dto\AccountDeletion;
use App\Dto\DisplayPreferencesInput;
use App\Dto\PasswordChange;
use App\Dto\ProfileUpdate;
use App\Entity\User;
use App\Media\ImageProcessor;
use App\Media\ImageStorage;
use App\Media\InvalidImageException;
use App\Preference\Language;
use App\Preference\Theme;
use App\Profile\AccountDeleter;
use App\Profile\PasswordChanger;
use App\Profile\PersonalDataExporter;
use App\Profile\ProfileView;
use App\Security\RequestThrottle;
use App\ValueObject\DisplayName;
use App\ValueObject\PhoneNumber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Son propre compte : ouvert dès la confirmation de l'adresse, avant la validation par le bureau */
#[Route('/api/me', name: 'profile_', format: 'json')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    private const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly RequestThrottle $requestThrottle,
        private readonly EntityManagerInterface $em,
        private readonly RateLimiterFactoryInterface $sensitiveActionLimiter,
        private readonly RateLimiterFactoryInterface $avatarUploadLimiter,
        private readonly ProfileView $view,
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->view->of($user));
    }

    #[Route('', name: 'update', methods: ['PATCH'])]
    public function update(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] ProfileUpdate $input, Request $request): JsonResponse
    {
        $payload = $request->toArray();

        if (null !== $input->displayName) {
            $user->rename(self::valid(static fn () => DisplayName::fromString($input->displayName)));
        }
        if (\array_key_exists('phone', $payload)) {
            $phone = $input->phone;
            $user->changePhone(null === $phone || '' === trim($phone) ? null : self::valid(static fn () => PhoneNumber::fromInput($phone)));
        }

        $this->em->flush();

        return $this->show($user);
    }

    /** Langue et thème choisis dans l'interface ; la langue sert aussi aux emails et notifications. */
    #[Route('/preferences', name: 'preferences', methods: ['PUT'])]
    public function updatePreferences(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] DisplayPreferencesInput $input): JsonResponse
    {
        $user->changeDisplayPreferences(Language::from($input->language), Theme::from($input->theme));
        $this->em->flush();

        return $this->show($user);
    }

    #[Route('/password', name: 'password', methods: ['POST'])]
    public function changePassword(
        #[CurrentUser] User $user,
        #[MapRequestPayload(acceptFormat: 'json')] PasswordChange $input,
        PasswordChanger $passwords,
        Security $security,
    ): Response {
        $this->throttle($user);
        $passwords->change($user, $input);

        // Les autres sessions sont invalidées (empreinte du hash changée) ; on garde celle-ci
        $security->login($user, 'json_login', 'main');

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/avatar', name: 'avatar_upload', methods: ['POST'])]
    public function uploadAvatar(#[CurrentUser] User $user, Request $request, ImageProcessor $processor, ImageStorage $avatarStorage): JsonResponse
    {
        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new InvalidImageException('Fichier « avatar » manquant.');
        }
        if ($file->getSize() > self::MAX_AVATAR_BYTES) {
            throw new InvalidImageException('Image trop lourde (2 Mo maximum).');
        }
        // Décoder une image coûte de la mémoire et du temps : 10 avatars par heure
        $this->requestThrottle->consume($this->avatarUploadLimiter, $user->getId()->toRfc4122());

        $previous = $user->avatar();
        $user->changeAvatar($avatarStorage->store($processor->avatar($file->getPathname())));
        $this->em->flush();

        if (null !== $previous) {
            $avatarStorage->delete($previous);
        }

        return $this->show($user);
    }

    #[Route('/avatar', name: 'avatar_delete', methods: ['DELETE'])]
    public function deleteAvatar(#[CurrentUser] User $user, ImageStorage $avatarStorage): JsonResponse
    {
        $previous = $user->avatar();
        $user->changeAvatar(null);
        $this->em->flush();

        if (null !== $previous) {
            $avatarStorage->delete($previous);
        }

        return $this->show($user);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(#[CurrentUser] User $user, PersonalDataExporter $exporter): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->setData($exporter->export($user));
        $response->headers->set('Content-Disposition', 'attachment; filename="mes-donnees-skull-darts-71.json"');

        return $response;
    }

    #[Route('', name: 'delete', methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
        #[MapRequestPayload(acceptFormat: 'json')] AccountDeletion $input,
        UserPasswordHasherInterface $hasher,
        AccountDeleter $deleter,
        Security $security,
    ): Response {
        $this->throttle($user);

        if ($user->hasPassword() && !$hasher->isPasswordValid($user, $input->password)) {
            throw new UnprocessableEntityHttpException('Mot de passe incorrect.');
        }

        $security->logout(validateCsrfToken: false);
        $deleter->delete($user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Limite les tentatives sur les actions protégées par mot de passe. */
    private function throttle(User $user): void
    {
        $this->requestThrottle->consume($this->sensitiveActionLimiter, $user->getId()->toRfc4122());
    }

    /**
     * @template T
     *
     * @param callable(): T $factory
     *
     * @return T
     */
    private static function valid(callable $factory): mixed
    {
        try {
            return $factory();
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
