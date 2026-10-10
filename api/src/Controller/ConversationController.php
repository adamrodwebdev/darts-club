<?php

namespace App\Controller;

use App\Dto\MessageInput;
use App\Dto\NewConversation;
use App\Entity\Conversation;
use App\Entity\User;
use App\Messaging\Conversations;
use App\Messaging\Mailbox;
use App\Messaging\MailboxAccess;
use App\Messaging\MessageBody;
use App\Messaging\MessagingView;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ConversationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/conversations', name: 'conversations_', format: 'json')]
#[IsGranted('ROLE_MEMBER')]
final class ConversationController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly Conversations $conversations,
        private readonly MessageRepository $messages,
        private readonly RateLimiterFactoryInterface $messagePostLimiter,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, ConversationRepository $repository, MailboxAccess $access): JsonResponse
    {
        $items = array_map(
            fn (Conversation $c): array => MessagingView::conversation($c, $user, $this->messages->latest($c), $this->messages->countUnread($c, $user)),
            $repository->findVisibleTo($user, $access->mailboxesManagedBy($user)),
        );

        return $this->json([
            'items' => $items,
            'mailboxes' => array_map(static fn (Mailbox $m): array => ['id' => $m->value, 'label' => $m->label()], Mailbox::cases()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[CurrentUser] User $user, #[MapRequestPayload] NewConversation $input, UserRepository $users): JsonResponse
    {
        $this->throttle($user);
        $body = self::body($input->body);

        if (null !== $input->mailbox) {
            $conversation = $this->conversations->writeToMailbox($user, Mailbox::from($input->mailbox), $input->subject, $body);
        } else {
            $recipient = $users->find((string) $input->recipientId);
            if (null === $recipient || !$recipient->isVerified() || $recipient->getId()->equals($user->getId())) {
                throw new UnprocessableEntityHttpException('Destinataire introuvable.');
            }
            $conversation = $this->conversations->writeTo($user, $recipient, $body);
        }

        return $this->json(
            MessagingView::conversation($conversation, $user, $this->messages->latest($conversation), 0),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}/messages', name: 'messages', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(ConversationVoter::ACCESS, subject: 'conversation')]
    public function messages(Conversation $conversation, #[CurrentUser] User $user, #[MapQueryParameter] ?string $before = null): JsonResponse
    {
        $cursor = null !== $before && Uuid::isValid($before) ? Uuid::fromString($before) : null;
        $page = $this->messages->page($conversation, $cursor);

        return $this->json([
            'conversation' => MessagingView::conversation($conversation, $user, $page[0] ?? null, $this->messages->countUnread($conversation, $user)),
            // Ordre chronologique pour l'affichage
            'items' => array_reverse(array_map(static fn ($m): array => MessagingView::message($m, $user), $page)),
            'nextCursor' => \count($page) > 0 ? end($page)->getId()->toRfc4122() : null,
        ]);
    }

    #[Route('/{id}/messages', name: 'reply', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[IsGranted(ConversationVoter::ACCESS, subject: 'conversation')]
    public function reply(Conversation $conversation, #[CurrentUser] User $user, #[MapRequestPayload] MessageInput $input): JsonResponse
    {
        $this->throttle($user);
        $this->conversations->reply($conversation, $user, self::body($input->body));

        $latest = $this->messages->latest($conversation);
        \assert(null !== $latest);

        return $this->json(MessagingView::message($latest, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}/read', name: 'read', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[IsGranted(ConversationVoter::ACCESS, subject: 'conversation')]
    public function read(Conversation $conversation, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $conversation->markReadBy($user);
        $em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function throttle(User $user): void
    {
        $limit = $this->messagePostLimiter->create($user->getId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }
    }

    private static function body(string $text): MessageBody
    {
        try {
            return MessageBody::fromString($text);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
